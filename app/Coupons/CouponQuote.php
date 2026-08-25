<?php

namespace App\Coupons;

/**
 * Pure quote result for a validated coupon (CPN-A).
 *
 * No public SKU or exact inventory quantity.
 *
 * @phpstan-type VendorAllocation array<int, int>
 */
final readonly class CouponQuote
{
    /**
     * @param  array<int, int>  $discountByVendorId  vendor_id => discount minor units
     */
    public function __construct(
        public int $couponId,
        public string $code,
        public string $scope,
        public ?int $vendorId,
        public string $currencyCode,
        public int $eligibleSubtotalMinor,
        public int $discountTotalMinor,
        public array $discountByVendorId,
    ) {}

    /**
     * @return array{
     *     coupon_id: int,
     *     code: string,
     *     scope: string,
     *     vendor_id: int|null,
     *     currency_code: string,
     *     eligible_subtotal_minor: int,
     *     discount_total_minor: int,
     *     discount_by_vendor_id: array<int, int>
     * }
     */
    public function toArray(): array
    {
        return [
            'coupon_id' => $this->couponId,
            'code' => $this->code,
            'scope' => $this->scope,
            'vendor_id' => $this->vendorId,
            'currency_code' => $this->currencyCode,
            'eligible_subtotal_minor' => $this->eligibleSubtotalMinor,
            'discount_total_minor' => $this->discountTotalMinor,
            'discount_by_vendor_id' => $this->discountByVendorId,
        ];
    }
}
