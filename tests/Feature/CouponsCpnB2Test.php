<?php

namespace Tests\Feature;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CouponsCpnB2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CurrencySeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function platformPayload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'SAVE10',
            'scope' => CouponScope::Platform->value,
            'vendor_id' => null,
            'type' => CouponType::Percent->value,
            'value' => 10,
            'currency_code' => 'SYP',
            'starts_at' => null,
            'ends_at' => null,
            'min_eligible_amount_minor' => 0,
            'max_discount_amount_minor' => null,
            'global_usage_limit' => null,
            'per_user_usage_limit' => null,
            'is_active' => 1,
            'product_ids' => [],
            'category_ids' => [],
        ], $overrides);
    }

    public function test_guest_is_redirected_from_admin_coupon_routes(): void
    {
        $coupon = Coupon::factory()->platform()->create(['code' => 'GUEST1']);

        $this->get(route('admin.coupons.index'))->assertRedirect('/login');
        $this->get(route('admin.coupons.create'))->assertRedirect('/login');
        $this->post(route('admin.coupons.store'), $this->platformPayload())->assertRedirect('/login');
        $this->get(route('admin.coupons.show', $coupon))->assertRedirect('/login');
        $this->get(route('admin.coupons.edit', $coupon))->assertRedirect('/login');
        $this->put(route('admin.coupons.update', $coupon), $this->platformPayload([
            'code' => 'GUEST1',
        ]))->assertRedirect('/login');
        $this->patch(route('admin.coupons.status', $coupon), ['is_active' => 0])->assertRedirect('/login');
    }

    public function test_customer_cannot_manage_coupons(): void
    {
        $customer = User::factory()->create();
        $coupon = Coupon::factory()->platform()->create();

        $this->actingAs($customer)->get(route('admin.coupons.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.coupons.create'))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.coupons.store'), $this->platformPayload())->assertForbidden();
        $this->actingAs($customer)->get(route('admin.coupons.show', $coupon))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.coupons.edit', $coupon))->assertForbidden();
        $this->actingAs($customer)->put(route('admin.coupons.update', $coupon), $this->platformPayload([
            'code' => $coupon->code,
        ]))->assertForbidden();
        $this->actingAs($customer)->patch(route('admin.coupons.status', $coupon), ['is_active' => 0])->assertForbidden();
    }

    public function test_vendor_cannot_manage_coupons(): void
    {
        $vendorUser = $this->createVendorUser();
        $coupon = Coupon::factory()->platform()->create();

        $this->actingAs($vendorUser)->get(route('admin.coupons.index'))->assertForbidden();
        $this->actingAs($vendorUser)->post(route('admin.coupons.store'), $this->platformPayload([
            'code' => 'VENDORBLOCK',
        ]))->assertForbidden();
        $this->actingAs($vendorUser)->put(route('admin.coupons.update', $coupon), $this->platformPayload([
            'code' => $coupon->code,
        ]))->assertForbidden();
    }

    public function test_staff_can_create_platform_and_vendor_coupons(): void
    {
        $admin = User::factory()->admin()->create();
        $vendor = Vendor::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.coupons.store'), $this->platformPayload([
                'code' => 'plat10',
                'category_ids' => [$category->id],
                'min_eligible_amount_minor' => 5000,
                'max_discount_amount_minor' => 2000,
                'global_usage_limit' => 100,
                'per_user_usage_limit' => 2,
            ]))
            ->assertRedirect();

        $platform = Coupon::query()->where('code', 'PLAT10')->firstOrFail();
        $this->assertSame(CouponScope::Platform, $platform->scope);
        $this->assertNull($platform->vendor_id);
        $this->assertSame(CouponType::Percent, $platform->type);
        $this->assertSame(10, $platform->value);
        $this->assertSame('SYP', $platform->currency_code);
        $this->assertSame(5000, $platform->min_eligible_amount_minor);
        $this->assertSame(2000, $platform->max_discount_amount_minor);
        $this->assertSame(100, $platform->global_usage_limit);
        $this->assertSame(2, $platform->per_user_usage_limit);
        $this->assertTrue($platform->is_active);
        $this->assertEqualsCanonicalizing([$category->id], $platform->categories()->pluck('categories.id')->all());

        $this->actingAs($admin)
            ->post(route('admin.coupons.store'), $this->platformPayload([
                'code' => 'SHOP5',
                'scope' => CouponScope::Vendor->value,
                'vendor_id' => $vendor->id,
                'type' => CouponType::Fixed->value,
                'value' => 500,
            ]))
            ->assertRedirect();

        $vendorCoupon = Coupon::query()->where('code', 'SHOP5')->firstOrFail();
        $this->assertSame(CouponScope::Vendor, $vendorCoupon->scope);
        $this->assertSame($vendor->id, $vendorCoupon->vendor_id);
        $this->assertSame(CouponType::Fixed, $vendorCoupon->type);
        $this->assertSame(500, $vendorCoupon->value);
    }

    public function test_staff_can_list_show_update_and_toggle_status(): void
    {
        $admin = User::factory()->admin()->create();
        $coupon = Coupon::factory()->platform()->percent(15)->create([
            'code' => 'LIST15',
            'currency_code' => 'SYP',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.coupons.index'))
            ->assertOk()
            ->assertSee('LIST15', false);

        $this->actingAs($admin)
            ->get(route('admin.coupons.show', $coupon))
            ->assertOk()
            ->assertSee('LIST15', false);

        $this->actingAs($admin)
            ->get(route('admin.coupons.edit', $coupon))
            ->assertOk();

        $this->actingAs($admin)
            ->put(route('admin.coupons.update', $coupon), $this->platformPayload([
                'code' => 'list15',
                'value' => 20,
                'is_active' => 1,
            ]))
            ->assertRedirect(route('admin.coupons.edit', $coupon));

        $coupon->refresh();
        $this->assertSame('LIST15', $coupon->code);
        $this->assertSame(20, $coupon->value);

        $this->actingAs($admin)
            ->patch(route('admin.coupons.status', $coupon), ['is_active' => 0])
            ->assertRedirect();

        $this->assertFalse($coupon->fresh()->is_active);
        $this->assertFalse(Route::has('admin.coupons.destroy'));
    }

    public function test_invalid_admin_coupon_payloads_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        Coupon::factory()->platform()->create(['code' => 'TAKEN']);

        $this->actingAs($admin)
            ->post(route('admin.coupons.store'), $this->platformPayload([
                'code' => 'taken',
            ]))
            ->assertSessionHasErrors('code');

        $this->actingAs($admin)
            ->post(route('admin.coupons.store'), $this->platformPayload([
                'code' => 'BADPCT',
                'type' => CouponType::Percent->value,
                'value' => 150,
            ]))
            ->assertSessionHasErrors('value');

        $this->actingAs($admin)
            ->post(route('admin.coupons.store'), $this->platformPayload([
                'code' => 'NOVENDOR',
                'scope' => CouponScope::Vendor->value,
                'vendor_id' => null,
            ]))
            ->assertSessionHasErrors('vendor_id');

        $this->actingAs($admin)
            ->post(route('admin.coupons.store'), $this->platformPayload([
                'code' => 'BADCUR',
                'currency_code' => 'ZZZ',
            ]))
            ->assertSessionHasErrors('currency_code');

        $this->assertDatabaseMissing('coupons', ['code' => 'BADPCT']);
        $this->assertDatabaseMissing('coupons', ['code' => 'NOVENDOR']);
        $this->assertDatabaseMissing('coupons', ['code' => 'BADCUR']);
        $this->assertDatabaseCount('coupons', 1);
    }
}
