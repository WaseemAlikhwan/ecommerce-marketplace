<?php

namespace App\Coupons;

/**
 * One cart/checkout line candidate for coupon quoting (CPN-A).
 *
 * Intentionally omits SKU and exact inventory quantity.
 */
final readonly class CouponLineCandidate
{
    public function __construct(
        public int $productId,
        public int $vendorId,
        public ?int $categoryId,
        public string $currencyCode,
        public int $lineTotalAmountMinor,
    ) {}
}
