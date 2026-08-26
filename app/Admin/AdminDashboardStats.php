<?php

namespace App\Admin;

/**
 * Query-only admin KPI snapshot (ADM-A / OPEN-020 V1).
 *
 * Money amounts are integer minor units. No public SKU or exact inventory quantity.
 *
 * @phpstan-type StatusCounts array<string, int>
 * @phpstan-type CommissionByCurrency array<string, int>
 */
final readonly class AdminDashboardStats
{
    /**
     * @param  array{
     *     pending: int,
     *     confirmed: int,
     *     shipped: int,
     *     delivered: int,
     *     cancelled: int
     * }  $vendorOrdersByStatus
     * @param  array{
     *     pending: int,
     *     collected: int,
     *     cancelled: int
     * }  $codPaymentsByStatus
     * @param  array<string, int>  $recognizedCommissionAmountMinorByCurrency  currency_code => minor units
     */
    public function __construct(
        public int $pendingVendorApplications,
        public int $pendingProductReviews,
        public int $placedParentOrders,
        public array $vendorOrdersByStatus,
        public array $codPaymentsByStatus,
        public int $publishedProducts,
        public int $approvedVendors,
        public array $recognizedCommissionAmountMinorByCurrency,
    ) {}

    /**
     * @return array{
     *     pending_vendor_applications: int,
     *     pending_product_reviews: int,
     *     placed_parent_orders: int,
     *     vendor_orders_by_status: array{
     *         pending: int,
     *         confirmed: int,
     *         shipped: int,
     *         delivered: int,
     *         cancelled: int
     *     },
     *     cod_payments_by_status: array{
     *         pending: int,
     *         collected: int,
     *         cancelled: int
     *     },
     *     published_products: int,
     *     approved_vendors: int,
     *     recognized_commission_amount_minor_by_currency: array<string, int>
     * }
     */
    public function toArray(): array
    {
        return [
            'pending_vendor_applications' => $this->pendingVendorApplications,
            'pending_product_reviews' => $this->pendingProductReviews,
            'placed_parent_orders' => $this->placedParentOrders,
            'vendor_orders_by_status' => $this->vendorOrdersByStatus,
            'cod_payments_by_status' => $this->codPaymentsByStatus,
            'published_products' => $this->publishedProducts,
            'approved_vendors' => $this->approvedVendors,
            'recognized_commission_amount_minor_by_currency' => $this->recognizedCommissionAmountMinorByCurrency,
        ];
    }
}
