<?php

namespace Tests\Feature;

use App\Admin\AdminDashboardStats;
use App\Enums\ParentOrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\VendorOrderStatus;
use App\Enums\VendorStatus;
use App\Models\ParentOrder;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorApplication;
use App\Models\VendorOrder;
use App\Services\AdminDashboardStatsService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AdminOpsAdmATest extends TestCase
{
    use RefreshDatabase;

    private AdminDashboardStatsService $stats;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class]);
        $this->stats = app(AdminDashboardStatsService::class);
    }

    public function test_snapshot_aggregates_frozen_kpi_set(): void
    {
        $vendorUser = $this->createVendorUser();
        $store = $vendorUser->vendor->store;
        $product = Product::factory()->for($store)->create(['status' => ProductStatus::Draft]);

        VendorApplication::factory()->pending()->count(2)->create();
        VendorApplication::factory()->approved()->create();

        ProductReview::factory()->pending()->count(3)->create(['product_id' => $product->id]);
        ProductReview::factory()->approved()->create(['product_id' => $product->id]);

        ParentOrder::factory()->count(2)->create(['status' => ParentOrderStatus::Placed]);
        ParentOrder::factory()->create(['status' => ParentOrderStatus::Cancelled]);

        // VO fixtures hang under cancelled parents so they do not inflate placed Parent KPIs.
        $this->createVendorOrder($store, VendorOrderStatus::Pending);
        $this->createVendorOrder($store, VendorOrderStatus::Confirmed);
        $this->createVendorOrder($store, VendorOrderStatus::Confirmed);
        $this->createVendorOrder($store, VendorOrderStatus::Shipped);
        $this->createVendorOrder($store, VendorOrderStatus::Delivered);
        $this->createVendorOrder($store, VendorOrderStatus::Cancelled);
        // Processing is not in the frozen KPI status set.
        $this->createVendorOrder($store, VendorOrderStatus::Processing);

        Payment::factory()->for($this->createVendorOrder($store, VendorOrderStatus::Pending))->create([
            'method' => PaymentMethod::Cod,
            'status' => PaymentStatus::Pending,
        ]);
        Payment::factory()->for($this->createVendorOrder($store, VendorOrderStatus::Delivered))->create([
            'method' => PaymentMethod::Cod,
            'status' => PaymentStatus::Collected,
            'collected_at' => now(),
        ]);
        Payment::factory()->for($this->createVendorOrder($store, VendorOrderStatus::Cancelled))->create([
            'method' => PaymentMethod::Cod,
            'status' => PaymentStatus::Cancelled,
        ]);

        Product::factory()->for($store)->create(['status' => ProductStatus::Published]);
        Product::factory()->for($store)->create(['status' => ProductStatus::Published]);

        Vendor::factory()->suspended()->create();

        $snapshot = $this->stats->snapshot();

        $this->assertSame(2, $snapshot->pendingVendorApplications);
        $this->assertSame(3, $snapshot->pendingProductReviews);
        $this->assertSame(2, $snapshot->placedParentOrders);
        $this->assertSame([
            'pending' => 2,
            'confirmed' => 2,
            'shipped' => 1,
            'delivered' => 2,
            'cancelled' => 2,
        ], $snapshot->vendorOrdersByStatus);
        $this->assertSame([
            'pending' => 1,
            'collected' => 1,
            'cancelled' => 1,
        ], $snapshot->codPaymentsByStatus);
        $this->assertSame(2, $snapshot->publishedProducts);
        $this->assertSame(1, $snapshot->approvedVendors);
        $this->assertSame(1, Vendor::query()->where('status', VendorStatus::Suspended)->count());
    }

    public function test_recognized_commission_sums_only_recognized_vos_per_currency(): void
    {
        $sypVendor = $this->createVendorUser();
        $sypStore = $sypVendor->vendor->store;
        $sypStore->forceFill(['default_currency_code' => 'SYP'])->save();

        $usdVendor = $this->createVendorUser();
        $usdStore = $usdVendor->vendor->store;
        $usdStore->forceFill(['default_currency_code' => 'USD'])->save();

        $this->createVendorOrder($sypStore, VendorOrderStatus::Delivered, [
            'currency_code' => 'SYP',
            'commission_amount_minor' => 1_000,
            'commission_recognized_at' => now(),
        ]);
        $this->createVendorOrder($sypStore, VendorOrderStatus::Delivered, [
            'currency_code' => 'SYP',
            'commission_amount_minor' => 500,
            'commission_recognized_at' => now(),
        ]);
        $unrecognized = $this->createVendorOrder($sypStore, VendorOrderStatus::Pending, [
            'currency_code' => 'SYP',
            'commission_amount_minor' => 9_999,
            'commission_recognized_at' => null,
        ]);
        $this->createVendorOrder($usdStore, VendorOrderStatus::Delivered, [
            'currency_code' => 'USD',
            'commission_amount_minor' => 250,
            'commission_recognized_at' => now(),
        ]);

        $this->assertNull($unrecognized->fresh()->commission_recognized_at);

        $snapshot = $this->stats->snapshot();

        $this->assertSame([
            'SYP' => 1_500,
            'USD' => 250,
        ], $snapshot->recognizedCommissionAmountMinorByCurrency);
    }

    public function test_payload_omits_sku_and_exact_inventory_keys(): void
    {
        $payload = $this->stats->snapshot()->toArray();
        $json = json_encode($payload);
        $this->assertIsString($json);

        $this->assertArrayNotHasKey('sku', $payload);
        $this->assertArrayNotHasKey('quantity', $payload);
        $this->assertArrayNotHasKey('stock', $payload);
        $this->assertStringNotContainsString('"sku"', $json);
        $this->assertStringNotContainsString('"quantity"', $json);
        $this->assertStringNotContainsString('"stock"', $json);

        foreach (array_keys($payload) as $key) {
            $this->assertDoesNotMatchRegularExpression('/sku|quantity|inventory|stock/i', (string) $key);
        }
    }

    public function test_admin_dashboard_policy_allows_staff_only(): void
    {
        $admin = User::factory()->admin()->create();
        $superAdmin = User::factory()->superAdmin()->create();
        $customer = User::factory()->create();
        $vendor = $this->createVendorUser();

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', AdminDashboardStats::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('viewAny', AdminDashboardStats::class));
        $this->assertTrue(Gate::forUser($admin)->allows('view', AdminDashboardStats::class));

        $this->assertTrue(Gate::forUser($customer)->denies('viewAny', AdminDashboardStats::class));
        $this->assertTrue(Gate::forUser($vendor)->denies('viewAny', AdminDashboardStats::class));
        $this->assertTrue(Gate::forUser($customer)->denies('view', AdminDashboardStats::class));
        $this->assertTrue(Gate::forUser($vendor)->denies('view', AdminDashboardStats::class));
    }

    public function test_empty_database_returns_zero_counts_and_empty_commission_map(): void
    {
        $snapshot = $this->stats->snapshot();

        $this->assertSame(0, $snapshot->pendingVendorApplications);
        $this->assertSame(0, $snapshot->pendingProductReviews);
        $this->assertSame(0, $snapshot->placedParentOrders);
        $this->assertSame([
            'pending' => 0,
            'confirmed' => 0,
            'shipped' => 0,
            'delivered' => 0,
            'cancelled' => 0,
        ], $snapshot->vendorOrdersByStatus);
        $this->assertSame([
            'pending' => 0,
            'collected' => 0,
            'cancelled' => 0,
        ], $snapshot->codPaymentsByStatus);
        $this->assertSame(0, $snapshot->publishedProducts);
        $this->assertSame(0, $snapshot->approvedVendors);
        $this->assertSame([], $snapshot->recognizedCommissionAmountMinorByCurrency);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createVendorOrder(
        Store $store,
        VendorOrderStatus $status,
        array $overrides = [],
    ): VendorOrder {
        $parent = ParentOrder::factory()->create([
            'status' => ParentOrderStatus::Cancelled,
        ]);

        return VendorOrder::factory()
            ->forStore($store)
            ->for($parent)
            ->create(array_merge([
                'status' => $status,
            ], $overrides));
    }
}
