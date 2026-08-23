<?php

namespace App\Services;

use App\Cart\CartLine;
use App\Cart\CartMergeAdjustment;
use App\Cart\CartMergeResult;
use App\Cart\CartMergeTransactionHook;
use App\Cart\CartMergeUnavailable;
use App\Cart\CartMutationResult;
use App\Cart\SessionCartStore;
use App\Exceptions\CartException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function __construct(
        private readonly SessionCartStore $sessionCart,
        private readonly CartMergeTransactionHook $mergeHook,
    ) {}

    /**
     * @return Collection<int, CartLine>
     */
    public function lines(?User $user): Collection
    {
        if ($user !== null) {
            return $this->databaseLines($user);
        }

        return collect($this->sessionCart->lines())
            ->map(fn (int $quantity, int $variantId): CartLine => new CartLine($variantId, $quantity))
            ->values();
    }

    public function add(?User $user, int $variantId, int $quantity): CartMutationResult
    {
        $this->assertPositiveQuantity($quantity);

        if ($user !== null) {
            return $this->mutateDatabase($user, $variantId, $quantity, sumExisting: true);
        }

        return $this->mutateSession($variantId, $quantity, sumExisting: true);
    }

    public function update(?User $user, int $variantId, int $quantity): CartMutationResult
    {
        if ($quantity < 0) {
            throw CartException::invalidQuantity();
        }

        if ($quantity === 0) {
            $this->remove($user, $variantId);

            return new CartMutationResult($variantId, 0, false);
        }

        if ($user !== null) {
            return $this->mutateDatabase($user, $variantId, $quantity, sumExisting: false);
        }

        return $this->mutateSession($variantId, $quantity, sumExisting: false);
    }

    public function remove(?User $user, int $variantId): void
    {
        if ($user !== null) {
            DB::transaction(function () use ($user, $variantId): void {
                $cart = $this->lockCart($user);

                CartItem::query()
                    ->where('cart_id', $cart->id)
                    ->where('variant_id', $variantId)
                    ->delete();
            });

            return;
        }

        $lines = $this->sessionCart->lines();
        unset($lines[$variantId]);
        $this->sessionCart->put($lines);
    }

    /**
     * Clear all lines for an authenticated cart (used after successful checkout).
     */
    public function clear(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $cart = $this->lockCart($user);
            CartItem::query()->where('cart_id', $cart->id)->delete();
        });
    }

    /**
     * Merge the guest session cart into the authenticated DB cart.
     *
     * Lock order: cart → variant (ascending ids) → item.
     * Guest session is cleared only after the DB transaction commits.
     */
    public function mergeGuestCart(User $user): CartMergeResult
    {
        $guestLines = $this->sessionCart->lines();

        if ($guestLines === []) {
            $this->sessionCart->clear();

            return new CartMergeResult([], [], []);
        }

        ksort($guestLines);

        $result = DB::transaction(function () use ($user, $guestLines): CartMergeResult {
            $cart = $this->lockCart($user);

            $kept = [];
            $adjusted = [];
            $unavailable = [];

            foreach ($guestLines as $variantId => $guestQuantity) {
                $stock = $this->inspectPurchasableStock($variantId, lockForUpdate: true);

                if ($stock['status'] !== 'ok') {
                    $unavailable[] = new CartMergeUnavailable($variantId, $stock['status']);

                    continue;
                }

                /** @var CartItem|null $item */
                $item = CartItem::query()
                    ->where('cart_id', $cart->id)
                    ->where('variant_id', $variantId)
                    ->lockForUpdate()
                    ->first();

                $existing = $item?->quantity ?? 0;
                $desired = $existing + $guestQuantity;
                $final = min($desired, $stock['quantity']);

                if ($final < 1) {
                    $unavailable[] = new CartMergeUnavailable(
                        $variantId,
                        CartMergeUnavailable::OUT_OF_STOCK,
                    );

                    continue;
                }

                if ($item === null) {
                    CartItem::query()->create([
                        'cart_id' => $cart->id,
                        'variant_id' => $variantId,
                        'quantity' => $final,
                    ]);
                } else {
                    $item->forceFill(['quantity' => $final])->save();
                }

                $kept[] = new CartLine($variantId, $final);

                if ($final < $desired) {
                    $adjusted[] = new CartMergeAdjustment($variantId, $desired, $final);
                }
            }

            $this->mergeHook->beforeCommit();

            return new CartMergeResult($kept, $adjusted, $unavailable);
        });

        $this->sessionCart->clear();

        return $result;
    }

    /**
     * @return Collection<int, CartLine>
     */
    private function databaseLines(User $user): Collection
    {
        $cart = Cart::query()->where('user_id', $user->id)->first();

        if ($cart === null) {
            return collect();
        }

        return $cart->items()
            ->orderBy('id')
            ->get(['variant_id', 'quantity'])
            ->map(fn (CartItem $item): CartLine => new CartLine(
                (int) $item->variant_id,
                (int) $item->quantity,
            ))
            ->values();
    }

    private function mutateSession(int $variantId, int $quantity, bool $sumExisting): CartMutationResult
    {
        $stock = $this->resolvePurchasableStock($variantId);
        $lines = $this->sessionCart->lines();
        $existing = $lines[$variantId] ?? 0;
        $desired = $sumExisting ? ($existing + $quantity) : $quantity;
        $final = min($desired, $stock);
        $lines[$variantId] = $final;
        $this->sessionCart->put($lines);

        return new CartMutationResult($variantId, $final, $final < $desired);
    }

    private function mutateDatabase(
        User $user,
        int $variantId,
        int $quantity,
        bool $sumExisting,
    ): CartMutationResult {
        return DB::transaction(function () use ($user, $variantId, $quantity, $sumExisting): CartMutationResult {
            $cart = $this->lockCart($user);
            $stock = $this->resolvePurchasableStock($variantId, lockForUpdate: true);

            /** @var CartItem|null $item */
            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            $existing = $item?->quantity ?? 0;
            $desired = $sumExisting ? ($existing + $quantity) : $quantity;
            $final = min($desired, $stock);

            if ($item === null) {
                CartItem::query()->create([
                    'cart_id' => $cart->id,
                    'variant_id' => $variantId,
                    'quantity' => $final,
                ]);
            } else {
                $item->forceFill(['quantity' => $final])->save();
            }

            return new CartMutationResult($variantId, $final, $final < $desired);
        });
    }

    private function lockCart(User $user): Cart
    {
        $now = now();

        Cart::query()->insertOrIgnore([
            'user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        /** @var Cart $cart */
        $cart = Cart::query()
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $cart;
    }

    private function resolvePurchasableStock(int $variantId, bool $lockForUpdate = false): int
    {
        $inspected = $this->inspectPurchasableStock($variantId, $lockForUpdate);

        if ($inspected['status'] !== 'ok') {
            throw CartException::unavailable();
        }

        return $inspected['quantity'];
    }

    /**
     * @return array{status: string, quantity: int}
     */
    private function inspectPurchasableStock(int $variantId, bool $lockForUpdate = false): array
    {
        $query = ProductVariant::query()->whereKey($variantId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        /** @var ProductVariant|null $variant */
        $variant = $query->first();

        if ($variant === null || $variant->trashed()) {
            return ['status' => CartMergeUnavailable::MISSING, 'quantity' => 0];
        }

        $visible = $variant->product()
            ->storefrontVisible()
            ->whereKey($variant->product_id)
            ->exists();

        if (! $visible) {
            return ['status' => CartMergeUnavailable::NOT_PURCHASABLE, 'quantity' => 0];
        }

        $stock = (int) $variant->quantity;

        if ($stock < 1) {
            return ['status' => CartMergeUnavailable::OUT_OF_STOCK, 'quantity' => 0];
        }

        return ['status' => 'ok', 'quantity' => $stock];
    }

    private function assertPositiveQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw CartException::invalidQuantity();
        }
    }
}
