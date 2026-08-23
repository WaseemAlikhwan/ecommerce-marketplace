<?php

namespace Tests\Feature;

use App\Enums\ParentOrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\VendorOrderStatus;
use App\Models\City;
use App\Models\CommissionSetting;
use App\Models\CustomerAddress;
use App\Models\Governorate;
use App\Models\OrderItem;
use App\Models\ParentOrder;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCommissionOverride;
use App\Models\VendorOrder;
use App\Support\PublicOrderCode;
use Database\Seeders\CommissionSettingSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\SyriaGeoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CheckoutChkASchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);
    }

    public function test_geo_tables_exist_without_area_level(): void
    {
        $this->assertTrue(Schema::hasTable('governorates'));
        $this->assertTrue(Schema::hasTable('cities'));
        $this->assertFalse(Schema::hasTable('areas'));
        $this->assertFalse(Schema::hasColumn('customer_addresses', 'area_id'));
    }

    public function test_syria_geo_seeder_loads_governorates_and_cities(): void
    {
        $this->seed(SyriaGeoSeeder::class);

        $this->assertSame(14, Governorate::query()->where('country_code', 'SY')->count());
        $this->assertGreaterThan(14, City::query()->count());
        $this->assertTrue(Governorate::query()->where('code', 'damascus')->exists());
        $this->assertTrue(City::query()->where('code', 'damascus-city')->exists());
    }

    public function test_commission_and_shipping_settings_are_configurable(): void
    {
        $this->seed(CommissionSettingSeeder::class);

        $this->assertSame(1000, CommissionSetting::currentRateBps());
        $this->assertTrue(Schema::hasColumn('stores', 'flat_shipping_amount_minor'));
        $this->assertIsArray(config('shipping.flat_fee_defaults_minor'));
        $this->assertArrayHasKey('SYP', config('shipping.flat_fee_defaults_minor'));
        $this->assertArrayHasKey('USD', config('shipping.flat_fee_defaults_minor'));

        $vendor = Vendor::factory()->create();
        VendorCommissionOverride::query()->create([
            'vendor_id' => $vendor->id,
            'rate_bps' => 750,
        ]);

        $this->assertDatabaseHas('vendor_commission_overrides', [
            'vendor_id' => $vendor->id,
            'rate_bps' => 750,
        ]);

        $store = Store::factory()->for($vendor)->create([
            'flat_shipping_amount_minor' => 15_000,
        ]);
        $this->assertSame(15_000, $store->fresh()->flat_shipping_amount_minor);
    }

    public function test_order_graph_persists_with_public_codes_and_money_integers(): void
    {
        $this->seed(SyriaGeoSeeder::class);

        $customer = User::factory()->create();
        $store = Store::factory()->create(['default_currency_code' => 'SYP']);
        $governorate = Governorate::query()->where('code', 'damascus')->firstOrFail();
        $city = City::query()->where('governorate_id', $governorate->id)->firstOrFail();

        $address = CustomerAddress::factory()->for($customer)->create([
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
            'is_default' => true,
        ]);

        $parent = ParentOrder::factory()->for($customer)->create([
            'public_code' => PublicOrderCode::parent(),
            'status' => ParentOrderStatus::Placed,
            'shipping_governorate_id' => $address->governorate_id,
            'shipping_city_id' => $address->city_id,
            'shipping_governorate_name_ar' => $governorate->name_ar,
            'shipping_governorate_name_en' => $governorate->name_en,
            'shipping_city_name_ar' => $city->name_ar,
            'shipping_city_name_en' => $city->name_en,
        ]);

        $this->assertStringStartsWith('PO-', $parent->public_code);

        $vendorOrder = VendorOrder::factory()->forStore($store)->create([
            'parent_order_id' => $parent->id,
            'public_code' => PublicOrderCode::vendor(),
            'status' => VendorOrderStatus::Pending,
            'items_subtotal_amount_minor' => 10_000,
            'shipping_amount_minor' => 1_500,
            'grand_total_amount_minor' => 11_500,
            'commission_rate_bps' => 1000,
            'commission_base_amount_minor' => 10_000,
            'commission_amount_minor' => 1_000,
        ]);

        $this->assertStringStartsWith('VO-', $vendorOrder->public_code);
        $this->assertSame($store->vendor_id, $vendorOrder->vendor_id);
        $this->assertSame(10_000, $vendorOrder->commission_base_amount_minor);
        $this->assertNull($vendorOrder->commission_recognized_at);

        $item = OrderItem::factory()->for($vendorOrder)->create([
            'quantity' => 2,
            'unit_price_amount_minor' => 5_000,
            'line_total_amount_minor' => 10_000,
            'currency_code' => 'SYP',
            'store_id' => $store->id,
            'vendor_id' => $store->vendor_id,
            'store_name' => $store->name,
        ]);

        $payment = Payment::factory()->create([
            'vendor_order_id' => $vendorOrder->id,
            'method' => PaymentMethod::Cod,
            'status' => PaymentStatus::Pending,
            'currency_code' => 'SYP',
            'amount_minor' => 11_500,
        ]);

        $this->assertTrue($parent->vendorOrders()->whereKey($vendorOrder)->exists());
        $this->assertTrue($vendorOrder->items()->whereKey($item)->exists());
        $this->assertTrue($vendorOrder->payment()->is($payment));
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(PaymentMethod::Cod, $payment->method);
    }

    public function test_public_codes_are_unique(): void
    {
        $this->seed(SyriaGeoSeeder::class);

        ParentOrder::factory()->create(['public_code' => 'PO-UNIQUE-A']);

        $this->expectException(QueryException::class);
        ParentOrder::factory()->create(['public_code' => 'PO-UNIQUE-A']);
    }

    public function test_order_and_payment_policies_deny_by_default_for_strangers(): void
    {
        $this->seed(SyriaGeoSeeder::class);

        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $vendorUser = $this->createVendorUser();
        $otherVendorUser = $this->createVendorUser();

        $parent = ParentOrder::factory()->for($owner)->create();
        $vendorOrder = VendorOrder::factory()
            ->forStore($vendorUser->vendor->store)
            ->create(['parent_order_id' => $parent->id]);
        $payment = Payment::factory()->create([
            'vendor_order_id' => $vendorOrder->id,
            'amount_minor' => $vendorOrder->grand_total_amount_minor,
            'currency_code' => $vendorOrder->currency_code,
        ]);
        $address = CustomerAddress::factory()->for($owner)->create();

        $this->assertTrue(Gate::forUser($owner)->allows('view', $parent));
        $this->assertFalse(Gate::forUser($stranger)->allows('view', $parent));
        $this->assertFalse(Gate::forUser($owner)->allows('create', ParentOrder::class));
        $this->assertFalse(Gate::forUser($owner)->allows('update', $parent));
        $this->assertFalse(Gate::forUser($owner)->allows('delete', $parent));

        $this->assertTrue(Gate::forUser($vendorUser)->allows('view', $vendorOrder));
        $this->assertTrue(Gate::forUser($owner)->allows('view', $vendorOrder));
        $this->assertFalse(Gate::forUser($otherVendorUser)->allows('view', $vendorOrder));
        $this->assertFalse(Gate::forUser($vendorUser)->allows('create', VendorOrder::class));
        $this->assertFalse(Gate::forUser($vendorUser)->allows('update', $vendorOrder));

        $this->assertTrue(Gate::forUser($vendorUser)->allows('view', $payment));
        $this->assertTrue(Gate::forUser($owner)->allows('view', $payment));
        $this->assertFalse(Gate::forUser($stranger)->allows('view', $payment));
        $this->assertFalse(Gate::forUser($vendorUser)->allows('update', $payment));
        $this->assertFalse(Gate::forUser($vendorUser)->allows('create', Payment::class));

        $this->assertTrue(Gate::forUser($owner)->allows('view', $address));
        $this->assertFalse(Gate::forUser($stranger)->allows('view', $address));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $address));
        $this->assertFalse(Gate::forUser($stranger)->allows('update', $address));
    }

    public function test_staff_can_view_orders_but_still_cannot_mutate_via_policy(): void
    {
        $this->seed(SyriaGeoSeeder::class);

        $staff = User::factory()->admin()->create();

        $parent = ParentOrder::factory()->create();
        $vendorOrder = VendorOrder::factory()->create(['parent_order_id' => $parent->id]);
        $payment = Payment::factory()->create(['vendor_order_id' => $vendorOrder->id]);

        $this->assertTrue(Gate::forUser($staff)->allows('view', $parent));
        $this->assertTrue(Gate::forUser($staff)->allows('view', $vendorOrder));
        $this->assertTrue(Gate::forUser($staff)->allows('view', $payment));
        $this->assertFalse(Gate::forUser($staff)->allows('update', $parent));
        $this->assertFalse(Gate::forUser($staff)->allows('update', $vendorOrder));
        $this->assertFalse(Gate::forUser($staff)->allows('update', $payment));
    }
}
