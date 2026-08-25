<?php

namespace App\Services;

use App\Checkout\PlaceOrderResult;
use App\Contracts\PaymentGateway;
use App\Contracts\ShippingCalculator;
use App\Coupons\CheckoutCouponSession;
use App\Coupons\CouponLineCandidate;
use App\Coupons\CouponQuote;
use App\Enums\ParentOrderStatus;
use App\Enums\VendorOrderStatus;
use App\Exceptions\CheckoutException;
use App\Exceptions\CouponException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CommissionSetting;
use App\Models\CustomerAddress;
use App\Models\OrderItem;
use App\Models\ParentOrder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCommissionOverride;
use App\Models\VendorOrder;
use App\Support\CheckedInteger;
use App\Support\PublicOrderCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function __construct(
        private readonly ShippingCalculator $shippingCalculator,
        private readonly PaymentGateway $paymentGateway,
        private readonly CouponService $coupons,
    ) {}

    public function placeOrder(User $user, CustomerAddress $address): PlaceOrderResult
    {
        if ((int) $address->user_id !== (int) $user->id) {
            throw CheckoutException::invalidAddress();
        }

        $address->loadMissing(['governorate', 'city']);

        if ($address->governorate === null || $address->city === null) {
            throw CheckoutException::invalidAddress();
        }

        if ((int) $address->city->governorate_id !== (int) $address->governorate_id) {
            throw CheckoutException::invalidAddress();
        }

        return DB::transaction(function () use ($user, $address): PlaceOrderResult {
            $cart = $this->lockCart($user);

            /** @var Collection<int, CartItem> $cartItems */
            $cartItems = CartItem::query()
                ->where('cart_id', $cart->id)
                ->orderBy('id')
                ->get();

            if ($cartItems->isEmpty()) {
                throw CheckoutException::emptyCart();
            }

            $resolvedLines = $this->resolveAndLockLines($cartItems);
            $groups = $this->groupLinesByVendor($resolvedLines);

            $couponCode = CheckoutCouponSession::get();
            $quote = null;
            if ($couponCode !== null) {
                try {
                    $quote = $this->coupons->validateAndQuote(
                        $user,
                        $couponCode,
                        $this->couponCandidatesFromResolved($resolvedLines),
                    );
                } catch (CouponException $e) {
                    CheckoutCouponSession::forget();
                    throw CheckoutException::couponRejected($e->errorCode);
                }
            }

            $parent = ParentOrder::query()->create([
                'public_code' => PublicOrderCode::parent(),
                'user_id' => $user->id,
                'status' => ParentOrderStatus::Placed,
                'coupon_id' => $quote?->couponId,
                'coupon_code' => $quote?->code,
                'shipping_recipient_name' => $address->recipient_name,
                'shipping_phone' => $address->phone,
                'shipping_governorate_id' => $address->governorate_id,
                'shipping_city_id' => $address->city_id,
                'shipping_governorate_name_ar' => $address->governorate->name_ar,
                'shipping_governorate_name_en' => $address->governorate->name_en,
                'shipping_city_name_ar' => $address->city->name_ar,
                'shipping_city_name_en' => $address->city->name_en,
                'shipping_country_code' => $address->governorate->country_code ?: 'SY',
                'shipping_line1' => $address->line1,
                'shipping_line2' => $address->line2,
                'shipping_notes' => $address->notes,
                'placed_at' => now(),
            ]);

            /** @var array<string, int> $dues */
            $dues = [];

            foreach ($groups as $group) {
                $vendorId = (int) $group['vendor']->id;
                $discountMinor = $quote !== null
                    ? (int) ($quote->discountByVendorId[$vendorId] ?? 0)
                    : 0;

                $vendorOrder = $this->createVendorOrder($parent, $address, $group, $quote, $discountMinor);
                $dues[$vendorOrder->currency_code] = CheckedInteger::add(
                    $dues[$vendorOrder->currency_code] ?? 0,
                    $vendorOrder->grand_total_amount_minor,
                );
                $this->paymentGateway->chargeVendorOrder($vendorOrder);
            }

            if ($quote !== null) {
                $this->coupons->redeem($user, $quote, $parent);
            }

            CartItem::query()->where('cart_id', $cart->id)->delete();
            CheckoutCouponSession::forget();

            ksort($dues);

            $parent = $parent->fresh(['vendorOrders.items', 'vendorOrders.payment']) ?? $parent;

            return new PlaceOrderResult($parent, $dues);
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

        return Cart::query()
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @param  Collection<int, CartItem>  $cartItems
     * @return list<array{
     *     variant: ProductVariant,
     *     product: Product,
     *     store: Store,
     *     vendor: Vendor,
     *     quantity: int,
     *     unitPriceMinor: int,
     *     lineTotalMinor: int,
     *     currencyCode: string,
     *     productNameAr: string,
     *     productNameEn: string,
     *     sku: string
     * }>
     */
    private function resolveAndLockLines(Collection $cartItems): array
    {
        $variantIds = $cartItems
            ->pluck('variant_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        /** @var Collection<int, ProductVariant> $variants */
        $variants = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->with(['product.translations', 'product.store.vendor'])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $resolved = [];

        foreach ($cartItems as $cartItem) {
            $variantId = (int) $cartItem->variant_id;
            $quantity = (int) $cartItem->quantity;
            $variant = $variants->get($variantId);

            if ($variant === null || $variant->trashed()) {
                throw CheckoutException::unavailableVariant();
            }

            $product = $variant->product;
            if ($product === null) {
                throw CheckoutException::unavailableVariant();
            }

            $visible = Product::query()
                ->storefrontVisible()
                ->whereKey($product->id)
                ->exists();

            if (! $visible) {
                throw CheckoutException::unavailableVariant();
            }

            $stock = (int) $variant->quantity;
            if ($stock < 1) {
                throw CheckoutException::unavailableVariant();
            }

            if ($quantity > $stock) {
                throw CheckoutException::insufficientStock();
            }

            $store = $product->store;
            $vendor = $store?->vendor;
            if ($store === null || $vendor === null) {
                throw CheckoutException::unavailableVariant();
            }

            $unitPrice = (int) $variant->price_amount_minor;
            $lineTotal = CheckedInteger::multiply($unitPrice, $quantity);

            $resolved[] = [
                'variant' => $variant,
                'product' => $product,
                'store' => $store,
                'vendor' => $vendor,
                'quantity' => $quantity,
                'unitPriceMinor' => $unitPrice,
                'lineTotalMinor' => $lineTotal,
                'currencyCode' => (string) $product->currency_code,
                'productNameAr' => $product->name('ar'),
                'productNameEn' => $product->name('en'),
                'sku' => (string) $variant->sku,
            ];
        }

        return $resolved;
    }

    /**
     * @param  list<array<string, mixed>>  $resolvedLines
     * @return list<array{
     *     vendor: Vendor,
     *     store: Store,
     *     currencyCode: string,
     *     lines: list<array<string, mixed>>
     * }>
     */
    private function groupLinesByVendor(array $resolvedLines): array
    {
        /** @var array<int, array{vendor: Vendor, store: Store, currencyCode: string, lines: list<array<string, mixed>>}> $groups */
        $groups = [];

        foreach ($resolvedLines as $line) {
            /** @var Vendor $vendor */
            $vendor = $line['vendor'];
            /** @var Store $store */
            $store = $line['store'];
            $vendorId = (int) $vendor->id;
            $currency = (string) $line['currencyCode'];

            if (! isset($groups[$vendorId])) {
                $groups[$vendorId] = [
                    'vendor' => $vendor,
                    'store' => $store,
                    'currencyCode' => $currency,
                    'lines' => [],
                ];
            }

            if ($groups[$vendorId]['currencyCode'] !== $currency) {
                throw CheckoutException::mixedCurrencyVendor();
            }

            if ((int) $groups[$vendorId]['store']->id !== (int) $store->id) {
                throw CheckoutException::unavailableVariant();
            }

            $groups[$vendorId]['lines'][] = $line;
        }

        ksort($groups);

        return array_values($groups);
    }

    /**
     * @param  array{
     *     vendor: Vendor,
     *     store: Store,
     *     currencyCode: string,
     *     lines: list<array<string, mixed>>
     * }  $group
     */
    private function createVendorOrder(
        ParentOrder $parent,
        CustomerAddress $address,
        array $group,
        ?CouponQuote $quote,
        int $discountMinor,
    ): VendorOrder {
        $itemsSubtotal = 0;
        foreach ($group['lines'] as $line) {
            $itemsSubtotal = CheckedInteger::add($itemsSubtotal, (int) $line['lineTotalMinor']);
        }

        $shippingMinor = max(0, $this->shippingCalculator->feeForVendorOrder(
            $group['vendor'],
            $group['store'],
            $group['currencyCode'],
        ));

        $discountMinor = max(0, min($discountMinor, $itemsSubtotal));

        $rateBps = $this->resolveCommissionRateBps((int) $group['vendor']->id);
        // Commission base stays pre-coupon item subtotal (OPEN-007 / CPN freeze).
        $commissionAmount = intdiv(CheckedInteger::multiply($itemsSubtotal, $rateBps), 10_000);
        $grandTotal = CheckedInteger::add(
            CheckedInteger::add($itemsSubtotal, -$discountMinor),
            $shippingMinor,
        );

        $vendorOrder = VendorOrder::query()->create([
            'public_code' => PublicOrderCode::vendor(),
            'parent_order_id' => $parent->id,
            'vendor_id' => $group['vendor']->id,
            'store_id' => $group['store']->id,
            'store_name' => $group['store']->name,
            'currency_code' => $group['currencyCode'],
            'status' => VendorOrderStatus::Pending,
            'items_subtotal_amount_minor' => $itemsSubtotal,
            'shipping_amount_minor' => $shippingMinor,
            'discount_amount_minor' => $discountMinor,
            'coupon_code' => $discountMinor > 0 ? $quote?->code : null,
            'coupon_id' => $discountMinor > 0 ? $quote?->couponId : null,
            'grand_total_amount_minor' => $grandTotal,
            'commission_rate_bps' => $rateBps,
            'commission_base_amount_minor' => $itemsSubtotal,
            'commission_amount_minor' => $commissionAmount,
            'commission_recognized_at' => null,
            'shipping_recipient_name' => $address->recipient_name,
            'shipping_phone' => $address->phone,
            'shipping_governorate_id' => $address->governorate_id,
            'shipping_city_id' => $address->city_id,
            'shipping_governorate_name_ar' => $address->governorate->name_ar,
            'shipping_governorate_name_en' => $address->governorate->name_en,
            'shipping_city_name_ar' => $address->city->name_ar,
            'shipping_city_name_en' => $address->city->name_en,
            'shipping_country_code' => $address->governorate->country_code ?: 'SY',
            'shipping_line1' => $address->line1,
            'shipping_line2' => $address->line2,
            'shipping_notes' => $address->notes,
        ]);

        foreach ($group['lines'] as $line) {
            OrderItem::query()->create([
                'vendor_order_id' => $vendorOrder->id,
                'product_id' => $line['product']->id,
                'variant_id' => $line['variant']->id,
                'store_id' => $group['store']->id,
                'vendor_id' => $group['vendor']->id,
                'quantity' => $line['quantity'],
                'unit_price_amount_minor' => $line['unitPriceMinor'],
                'line_total_amount_minor' => $line['lineTotalMinor'],
                'currency_code' => $line['currencyCode'],
                'product_name_ar' => $line['productNameAr'],
                'product_name_en' => $line['productNameEn'],
                'sku' => $line['sku'],
                'store_name' => $group['store']->name,
            ]);

            /** @var ProductVariant $variant */
            $variant = $line['variant'];
            $remaining = (int) $variant->quantity - (int) $line['quantity'];
            if ($remaining < 0) {
                throw CheckoutException::insufficientStock();
            }
            $variant->forceFill(['quantity' => $remaining])->save();
        }

        return $vendorOrder;
    }

    private function resolveCommissionRateBps(int $vendorId): int
    {
        $override = VendorCommissionOverride::query()
            ->where('vendor_id', $vendorId)
            ->value('rate_bps');

        if ($override !== null) {
            return (int) $override;
        }

        $rate = CommissionSetting::currentRateBps();
        if ($rate === null) {
            throw CheckoutException::commissionUnconfigured();
        }

        return $rate;
    }

    /**
     * @param  list<array<string, mixed>>  $resolvedLines
     * @return list<CouponLineCandidate>
     */
    private function couponCandidatesFromResolved(array $resolvedLines): array
    {
        $candidates = [];

        foreach ($resolvedLines as $line) {
            /** @var Product $product */
            $product = $line['product'];
            /** @var Vendor $vendor */
            $vendor = $line['vendor'];

            $candidates[] = new CouponLineCandidate(
                (int) $product->id,
                (int) $vendor->id,
                $product->category_id !== null ? (int) $product->category_id : null,
                (string) $line['currencyCode'],
                (int) $line['lineTotalMinor'],
            );
        }

        return $candidates;
    }
}
