<?php

namespace Tests\Feature;

use App\Enums\ParentOrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\VendorOrderStatus;
use App\Models\ParentOrder;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorOrder;
use App\Support\Locale;
use App\Support\Money;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOpsAdmBTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CurrencySeeder::class]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_non_staff_receives_forbidden(): void
    {
        $customer = User::factory()->create();
        $vendor = $this->createVendorUser();

        $this->actingAs($customer)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this->actingAs($vendor)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_staff_sees_frozen_kpis_and_module_links(): void
    {
        $admin = User::factory()->admin()->create(['preferred_locale' => 'en']);
        $vendorUser = $this->createVendorUser();
        $store = $vendorUser->vendor->store;
        $product = Product::factory()->for($store)->create(['status' => ProductStatus::Draft]);

        VendorApplication::factory()->pending()->count(2)->create();
        ProductReview::factory()->pending()->count(3)->create(['product_id' => $product->id]);
        ParentOrder::factory()->count(2)->create(['status' => ParentOrderStatus::Placed]);

        $this->createVendorOrder($store, VendorOrderStatus::Pending);
        $this->createVendorOrder($store, VendorOrderStatus::Confirmed);
        Payment::factory()->for($this->createVendorOrder($store, VendorOrderStatus::Pending))->create([
            'method' => PaymentMethod::Cod,
            'status' => PaymentStatus::Pending,
        ]);

        Product::factory()->for($store)->create(['status' => ProductStatus::Published]);
        Product::factory()->for($store)->create(['status' => ProductStatus::Published]);

        $recognizedSyp = $this->createVendorOrder($store, VendorOrderStatus::Delivered, [
            'currency_code' => 'SYP',
            'commission_amount_minor' => 1_500,
            'commission_recognized_at' => now(),
        ]);
        $usdStore = $store->fresh();
        $usdStore->forceFill(['default_currency_code' => 'USD'])->save();
        $recognizedUsd = $this->createVendorOrder($usdStore, VendorOrderStatus::Delivered, [
            'currency_code' => 'USD',
            'commission_amount_minor' => 250,
            'commission_recognized_at' => now(),
        ]);

        $this->assertSame('SYP', $recognizedSyp->fresh()->currency_code);
        $this->assertSame(1_500, $recognizedSyp->fresh()->commission_amount_minor);
        $this->assertSame('USD', $recognizedUsd->fresh()->currency_code);
        $this->assertSame(250, $recognizedUsd->fresh()->commission_amount_minor);

        $sypLabel = Money::formatFromMinor(1_500, 0).' SYP';
        $usdLabel = Money::formatFromMinor(250, 2).' USD';

        $response = $this->actingAs($admin)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSeeText('Pending applications');
        $response->assertSeeText('Pending product reviews');
        $response->assertSeeText('Placed parent orders');
        $response->assertSeeText('Published products');
        $response->assertSeeText('Approved vendors');
        $response->assertSeeText('Vendor orders by status');
        $response->assertSeeText('COD payments by status');
        $response->assertSeeText('Recognized commission');
        $response->assertSeeText($sypLabel);
        $response->assertSeeText($usdLabel);
        $response->assertSee(route('admin.vendors'), false);
        $response->assertSee(route('admin.reviews.index'), false);
        $response->assertSee(route('admin.coupons.index'), false);
        $response->assertSee(route('admin.catalog'), false);
        $response->assertSee(route('admin.orders'), false);
        $response->assertDontSeeText('Later');
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

        $order = VendorOrder::factory()
            ->forStore($store)
            ->for($parent)
            ->create(array_merge([
                'status' => $status,
            ], $overrides));

        // Factory afterCreating may rewrite currency from the store; re-apply money overrides.
        if ($overrides !== []) {
            $order->forceFill($overrides)->save();
        }

        return $order->fresh();
    }
}
