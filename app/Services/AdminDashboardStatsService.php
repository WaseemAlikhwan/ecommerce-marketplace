<?php

namespace App\Services;

use App\Admin\AdminDashboardStats;
use App\Enums\ParentOrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductReviewStatus;
use App\Enums\ProductStatus;
use App\Enums\VendorOrderStatus;
use App\Enums\VendorStatus;
use App\Models\ParentOrder;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Vendor;
use App\Models\VendorApplication;
use App\Models\VendorOrder;
use Illuminate\Support\Facades\DB;

/**
 * Query-only admin KPI aggregation (ADM-A / OPEN-020 V1).
 */
class AdminDashboardStatsService
{
    /**
     * @var list<VendorOrderStatus>
     */
    private const VO_KPI_STATUSES = [
        VendorOrderStatus::Pending,
        VendorOrderStatus::Confirmed,
        VendorOrderStatus::Shipped,
        VendorOrderStatus::Delivered,
        VendorOrderStatus::Cancelled,
    ];

    /**
     * @var list<PaymentStatus>
     */
    private const COD_PAYMENT_KPI_STATUSES = [
        PaymentStatus::Pending,
        PaymentStatus::Collected,
        PaymentStatus::Cancelled,
    ];

    public function snapshot(): AdminDashboardStats
    {
        return new AdminDashboardStats(
            pendingVendorApplications: VendorApplication::query()->pending()->count(),
            pendingProductReviews: ProductReview::query()
                ->where('status', ProductReviewStatus::Pending)
                ->count(),
            placedParentOrders: ParentOrder::query()
                ->where('status', ParentOrderStatus::Placed)
                ->count(),
            vendorOrdersByStatus: $this->vendorOrdersByStatus(),
            codPaymentsByStatus: $this->codPaymentsByStatus(),
            publishedProducts: Product::query()
                ->where('status', ProductStatus::Published)
                ->count(),
            approvedVendors: Vendor::query()
                ->where('status', VendorStatus::Approved)
                ->count(),
            recognizedCommissionAmountMinorByCurrency: $this->recognizedCommissionAmountMinorByCurrency(),
        );
    }

    /**
     * @return array{
     *     pending: int,
     *     confirmed: int,
     *     shipped: int,
     *     delivered: int,
     *     cancelled: int
     * }
     */
    private function vendorOrdersByStatus(): array
    {
        $counts = array_fill_keys(
            array_map(static fn (VendorOrderStatus $s): string => $s->value, self::VO_KPI_STATUSES),
            0,
        );

        $rows = VendorOrder::query()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->whereIn('status', self::VO_KPI_STATUSES)
            ->groupBy('status')
            ->get();

        foreach ($rows as $row) {
            $status = $row->status instanceof VendorOrderStatus
                ? $row->status->value
                : (string) $row->status;

            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row->aggregate;
            }
        }

        return $counts;
    }

    /**
     * @return array{
     *     pending: int,
     *     collected: int,
     *     cancelled: int
     * }
     */
    private function codPaymentsByStatus(): array
    {
        $counts = array_fill_keys(
            array_map(static fn (PaymentStatus $s): string => $s->value, self::COD_PAYMENT_KPI_STATUSES),
            0,
        );

        $rows = Payment::query()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->where('method', PaymentMethod::Cod)
            ->whereIn('status', self::COD_PAYMENT_KPI_STATUSES)
            ->groupBy('status')
            ->get();

        foreach ($rows as $row) {
            $status = $row->status instanceof PaymentStatus
                ? $row->status->value
                : (string) $row->status;

            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row->aggregate;
            }
        }

        return $counts;
    }

    /**
     * Snapshot commission only for VOs with commission_recognized_at set (BR-RPT-03).
     *
     * @return array<string, int> currency_code => sum of commission_amount_minor
     */
    private function recognizedCommissionAmountMinorByCurrency(): array
    {
        $rows = VendorOrder::query()
            ->select('currency_code', DB::raw('SUM(commission_amount_minor) as aggregate'))
            ->whereNotNull('commission_recognized_at')
            ->groupBy('currency_code')
            ->orderBy('currency_code')
            ->get();

        $sums = [];
        foreach ($rows as $row) {
            $sums[(string) $row->currency_code] = (int) $row->aggregate;
        }

        return $sums;
    }
}
