<?php

namespace Tests\Feature;

use App\Enums\ParentOrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\VendorOrderStatus;
use App\Models\OrderItem;
use App\Models\ParentOrder;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorOrder;
use App\Support\Locale;
use Database\Seeders\CommissionSettingSeeder;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADM-D brief smoke: staff dashboard KPIs → Parent show → Payment show.
 */
class AdminOpsAdmDSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_smoke_dashboard_parent_payment_path(): void
    {
        $this->seed([
            CurrencySeeder::class,
            CommissionSettingSeeder::class,
        ]);

        $admin = User::factory()->admin()->create(['preferred_locale' => 'en']);
        $customer = User::factory()->create(['name' => 'Smoke Customer']);
        $vendorUser = $this->createVendorUser();
        $store = $vendorUser->vendor->store;
        $product = Product::factory()->for($store)->create(['status' => ProductStatus::Published]);

        VendorApplication::factory()->pending()->create();
        ProductReview::factory()->pending()->create(['product_id' => $product->id]);

        $parent = ParentOrder::factory()->for($customer)->create([
            'status' => ParentOrderStatus::Placed,
        ]);

        $vendorOrder = VendorOrder::factory()
            ->forStore($store)
            ->for($parent)
            ->create([
                'status' => VendorOrderStatus::Pending,
                'currency_code' => 'SYP',
                'grand_total_amount_minor' => 5_000,
                'commission_amount_minor' => 500,
                'commission_recognized_at' => now(),
            ]);

        OrderItem::factory()->for($vendorOrder)->create([
            'sku' => 'SMOKE-SKU-SHOULD-NOT-LEAK',
            'product_name_en' => 'Smoke Line',
            'quantity' => 1,
            'currency_code' => 'SYP',
            'unit_price_amount_minor' => 5_000,
            'line_total_amount_minor' => 5_000,
            'store_name' => $store->name,
            'vendor_id' => $store->vendor_id,
            'store_id' => $store->id,
        ]);

        $payment = Payment::factory()->for($vendorOrder)->create([
            'method' => PaymentMethod::Cod,
            'status' => PaymentStatus::Pending,
            'amount_minor' => 5_000,
            'currency_code' => 'SYP',
        ]);

        $dashboard = $this->actingAs($admin)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('admin.dashboard'));
        $dashboard->assertOk();
        $dashboard->assertSeeText('Pending applications');
        $dashboard->assertSeeText('Placed parent orders');
        $dashboard->assertSeeText('Recognized commission');
        $dashboard->assertSeeText('500 SYP');
        $dashboard->assertSee(route('admin.orders'), false);
        $dashboard->assertSee(route('admin.payments.index'), false);

        $parentShow = $this->actingAs($admin)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('admin.orders.show', $parent));
        $parentShow->assertOk();
        $parentShow->assertSeeText($parent->public_code);
        $parentShow->assertSeeText('Smoke Line');
        $parentShow->assertDontSee('SMOKE-SKU-SHOULD-NOT-LEAK');

        $paymentShow = $this->actingAs($admin)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('admin.payments.show', $payment));
        $paymentShow->assertOk();
        $paymentShow->assertSeeText('COD pending');
        $paymentShow->assertSeeText($vendorOrder->public_code);
        $paymentShow->assertDontSeeText('Mark collected');
        $paymentShow->assertDontSee('SMOKE-SKU-SHOULD-NOT-LEAK');
    }
}
