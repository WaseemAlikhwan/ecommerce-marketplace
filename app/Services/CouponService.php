<?php

namespace App\Services;

use App\Coupons\CouponLineCandidate;
use App\Coupons\CouponQuote;
use App\Enums\CouponRedemptionStatus;
use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Enums\VendorOrderStatus;
use App\Exceptions\CouponException;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\ParentOrder;
use App\Models\User;
use App\Models\VendorOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Coupon validate/quote/redeem (CPN-A/B1 / OPEN-007 V1).
 *
 * Exactly one coupon code per checkout. Redeem runs inside the place-order transaction.
 */
class CouponService
{
    /**
     * Assert that applying $code does not violate the single-coupon-per-checkout freeze.
     *
     * @throws CouponException
     */
    public function assertSingleCodeAllowed(?string $alreadyAppliedCode, string $code): void
    {
        $incoming = $this->normalizeCode($code);
        $existing = $alreadyAppliedCode !== null ? $this->normalizeCode($alreadyAppliedCode) : null;

        if ($existing !== null && $existing !== '' && $existing !== $incoming) {
            throw CouponException::conflict();
        }
    }

    /**
     * Validate and quote a coupon against checkout line candidates.
     *
     * @param  list<CouponLineCandidate>  $lines
     *
     * @throws CouponException
     */
    public function validateAndQuote(
        User $user,
        string $code,
        array $lines,
        ?string $alreadyAppliedCode = null,
        ?Carbon $at = null,
    ): CouponQuote {
        $this->assertSingleCodeAllowed($alreadyAppliedCode, $code);

        $normalized = $this->normalizeCode($code);
        if ($normalized === '') {
            throw CouponException::invalid();
        }

        /** @var Coupon|null $coupon */
        $coupon = Coupon::query()
            ->where('code', $normalized)
            ->with(['products:id', 'categories:id'])
            ->first();

        if ($coupon === null) {
            throw CouponException::notFound();
        }

        $this->assertCouponUsable($coupon, $user, $at ?? now());

        $eligible = $this->eligibleLines($coupon, $lines);
        if ($eligible->isEmpty()) {
            throw CouponException::currency();
        }

        $eligibleSubtotal = (int) $eligible->sum(fn (CouponLineCandidate $line): int => $line->lineTotalAmountMinor);

        if ($eligibleSubtotal <= 0) {
            throw CouponException::currency();
        }

        if ($eligibleSubtotal < (int) $coupon->min_eligible_amount_minor) {
            throw CouponException::minNotMet();
        }

        $discountTotal = $this->computeDiscount($coupon, $eligibleSubtotal);
        if ($discountTotal <= 0) {
            throw CouponException::invalid();
        }

        $allocation = $this->allocateByVendor($eligible, $eligibleSubtotal, $discountTotal);

        return new CouponQuote(
            couponId: (int) $coupon->id,
            code: (string) $coupon->code,
            scope: $coupon->scope->value,
            vendorId: $coupon->vendor_id !== null ? (int) $coupon->vendor_id : null,
            currencyCode: (string) $coupon->currency_code,
            eligibleSubtotalMinor: $eligibleSubtotal,
            discountTotalMinor: $discountTotal,
            discountByVendorId: $allocation,
        );
    }

    /**
     * Persist an active redemption for the placed Parent Order (counts toward limits).
     * Must run inside the place-order DB transaction after VOs exist.
     */
    public function redeem(User $user, CouponQuote $quote, ParentOrder $parentOrder): CouponRedemption
    {
        /** @var Coupon $coupon */
        $coupon = Coupon::query()->whereKey($quote->couponId)->lockForUpdate()->firstOrFail();

        $this->assertCouponUsable($coupon, $user, now());

        return CouponRedemption::query()->create([
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'parent_order_id' => $parentOrder->id,
            'vendor_order_id' => null,
            'discount_amount_minor' => $quote->discountTotalMinor,
            'currency_code' => $quote->currencyCode,
            'status' => CouponRedemptionStatus::Active,
        ]);
    }

    /**
     * Release active redemption(s) for a Parent Order (cancel path).
     */
    public function releaseForParentOrder(ParentOrder $parentOrder): int
    {
        return CouponRedemption::query()
            ->where('parent_order_id', $parentOrder->id)
            ->where('status', CouponRedemptionStatus::Active)
            ->update(['status' => CouponRedemptionStatus::Released]);
    }

    /**
     * Release active redemption(s) tied to a Vendor Order, or the parent redemption
     * when the Parent Order has no remaining non-cancelled Vendor Orders.
     */
    public function releaseAfterVendorOrderCancelled(VendorOrder $vendorOrder): int
    {
        $released = CouponRedemption::query()
            ->where('vendor_order_id', $vendorOrder->id)
            ->where('status', CouponRedemptionStatus::Active)
            ->update(['status' => CouponRedemptionStatus::Released]);

        $parentId = (int) $vendorOrder->parent_order_id;
        $stillActive = VendorOrder::query()
            ->where('parent_order_id', $parentId)
            ->where('status', '!=', VendorOrderStatus::Cancelled->value)
            ->exists();

        if (! $stillActive) {
            $released += $this->releaseForParentOrder(
                ParentOrder::query()->whereKey($parentId)->firstOrFail()
            );
        }

        return $released;
    }

    /**
     * @throws CouponException
     */
    private function assertCouponUsable(Coupon $coupon, User $user, Carbon $at): void
    {
        if (! $coupon->is_active) {
            throw CouponException::inactive();
        }

        if ($coupon->scope === CouponScope::Vendor && $coupon->vendor_id === null) {
            throw CouponException::invalid();
        }

        if ($coupon->scope === CouponScope::Platform && $coupon->vendor_id !== null) {
            throw CouponException::invalid();
        }

        if ($coupon->type === CouponType::Percent) {
            $percent = (int) $coupon->value;
            if ($percent < 1 || $percent > 100) {
                throw CouponException::invalid();
            }
        }

        if ($coupon->type === CouponType::Fixed && (int) $coupon->value < 1) {
            throw CouponException::invalid();
        }

        if ($coupon->starts_at !== null && $at->lt($coupon->starts_at)) {
            throw CouponException::expired();
        }

        if ($coupon->ends_at !== null && $at->gt($coupon->ends_at)) {
            throw CouponException::expired();
        }

        if ($coupon->global_usage_limit !== null) {
            $globalUsed = $coupon->redemptions()
                ->where('status', CouponRedemptionStatus::Active)
                ->count();

            if ($globalUsed >= (int) $coupon->global_usage_limit) {
                throw CouponException::limit();
            }
        }

        if ($coupon->per_user_usage_limit !== null) {
            $userUsed = $coupon->redemptions()
                ->where('user_id', $user->id)
                ->where('status', CouponRedemptionStatus::Active)
                ->count();

            if ($userUsed >= (int) $coupon->per_user_usage_limit) {
                throw CouponException::limit();
            }
        }
    }

    /**
     * @param  list<CouponLineCandidate>  $lines
     * @return Collection<int, CouponLineCandidate>
     */
    private function eligibleLines(Coupon $coupon, array $lines): Collection
    {
        $productIds = $coupon->products->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $categoryIds = $coupon->categories->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $restrictProducts = $productIds !== [];
        $restrictCategories = $categoryIds !== [];

        return collect($lines)
            ->filter(function (CouponLineCandidate $line) use ($coupon, $restrictProducts, $restrictCategories, $productIds, $categoryIds): bool {
                if ($line->lineTotalAmountMinor <= 0) {
                    return false;
                }

                if (strcasecmp($line->currencyCode, $coupon->currency_code) !== 0) {
                    return false;
                }

                if ($coupon->scope === CouponScope::Vendor
                    && (int) $line->vendorId !== (int) $coupon->vendor_id) {
                    return false;
                }

                if ($restrictProducts || $restrictCategories) {
                    $productOk = $restrictProducts && in_array($line->productId, $productIds, true);
                    $categoryOk = $restrictCategories
                        && $line->categoryId !== null
                        && in_array($line->categoryId, $categoryIds, true);

                    if (! $productOk && ! $categoryOk) {
                        return false;
                    }
                }

                return true;
            })
            ->values();
    }

    private function computeDiscount(Coupon $coupon, int $eligibleSubtotal): int
    {
        $discount = match ($coupon->type) {
            CouponType::Percent => intdiv($eligibleSubtotal * (int) $coupon->value, 100),
            CouponType::Fixed => min((int) $coupon->value, $eligibleSubtotal),
        };

        if ($coupon->max_discount_amount_minor !== null) {
            $discount = min($discount, (int) $coupon->max_discount_amount_minor);
        }

        return max(0, $discount);
    }

    /**
     * Allocate discount across vendors by eligible line share (largest-remainder).
     *
     * @param  Collection<int, CouponLineCandidate>  $eligible
     * @return array<int, int>
     */
    private function allocateByVendor(Collection $eligible, int $eligibleSubtotal, int $discountTotal): array
    {
        /** @var array<int, int> $byVendor */
        $byVendor = [];
        foreach ($eligible as $line) {
            $byVendor[$line->vendorId] = ($byVendor[$line->vendorId] ?? 0) + $line->lineTotalAmountMinor;
        }

        if ($eligibleSubtotal <= 0 || $discountTotal <= 0) {
            return array_fill_keys(array_keys($byVendor), 0);
        }

        $raw = [];
        $floors = [];
        $remainder = $discountTotal;

        foreach ($byVendor as $vendorId => $subtotal) {
            $exact = ($discountTotal * $subtotal) / $eligibleSubtotal;
            $floor = (int) floor($exact);
            $floors[$vendorId] = $floor;
            $raw[$vendorId] = $exact - $floor;
            $remainder -= $floor;
        }

        arsort($raw, SORT_NUMERIC);
        foreach (array_keys($raw) as $vendorId) {
            if ($remainder <= 0) {
                break;
            }
            $floors[$vendorId]++;
            $remainder--;
        }

        return $floors;
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }
}
