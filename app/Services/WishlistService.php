<?php

namespace App\Services;

use App\Exceptions\WishlistException;
use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Authoritative customer Product wishlist (WSH-A / OPEN-018 V1).
 *
 * No public SKU or exact inventory quantity is exposed from this service.
 */
class WishlistService
{
    public function add(User $actor, Product $product): WishlistItem
    {
        $this->assertCustomer($actor);
        $this->assertStorefrontVisible($product);

        return DB::transaction(function () use ($actor, $product): WishlistItem {
            $existing = WishlistItem::query()
                ->where('user_id', $actor->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return WishlistItem::query()->create([
                'user_id' => $actor->id,
                'product_id' => $product->id,
            ]);
        });
    }

    public function remove(User $actor, Product $product): void
    {
        $this->assertCustomer($actor);

        WishlistItem::query()
            ->where('user_id', $actor->id)
            ->where('product_id', $product->id)
            ->delete();
    }

    /**
     * Owner-only list of wishlist rows whose products are currently storefront-visible.
     * Invisible products are omitted (no private store leakage).
     *
     * @return Collection<int, WishlistItem>
     */
    public function listFor(User $actor): Collection
    {
        $this->assertCustomer($actor);

        return WishlistItem::query()
            ->where('user_id', $actor->id)
            ->whereHas('product', fn ($query) => $query->storefrontVisible())
            ->latest('id')
            ->get();
    }

    /**
     * Count storefront-visible wishlist items for dashboard (matches listFor visibility).
     */
    public function countFor(User $actor): int
    {
        if (! $actor->isCustomer()) {
            return 0;
        }

        return WishlistItem::query()
            ->where('user_id', $actor->id)
            ->whereHas('product', fn ($query) => $query->storefrontVisible())
            ->count();
    }

    /**
     * Wishlist row id for this customer + product, or null when absent / non-customer.
     */
    public function itemIdFor(User $actor, Product $product): ?int
    {
        if (! $actor->isCustomer()) {
            return null;
        }

        $id = WishlistItem::query()
            ->where('user_id', $actor->id)
            ->where('product_id', $product->id)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function assertCustomer(User $actor): void
    {
        if (! $actor->isCustomer()) {
            throw WishlistException::unauthorized();
        }
    }

    private function assertStorefrontVisible(Product $product): void
    {
        $visible = Product::query()
            ->storefrontVisible()
            ->whereKey($product->id)
            ->exists();

        if (! $visible) {
            throw WishlistException::notFound();
        }
    }
}
