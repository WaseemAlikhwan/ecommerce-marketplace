<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Currency;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StoreCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_currency_reference_rows_exist_with_expected_exponents(): void
    {
        $this->assertDatabaseHas('currencies', [
            'code' => 'SYP',
            'exponent' => 0,
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('currencies', [
            'code' => 'USD',
            'exponent' => 2,
            'is_active' => 1,
        ]);

        $this->assertSame(0, Currency::query()->findOrFail('SYP')->exponent);
        $this->assertSame(2, Currency::query()->findOrFail('USD')->exponent);
    }

    public function test_new_and_factory_stores_default_to_syp(): void
    {
        $store = Store::factory()->create();

        $this->assertSame('SYP', $store->default_currency_code);
        $this->assertSame('SYP', $store->defaultCurrency->code);
    }

    public function test_vendor_approval_creates_store_with_syp_default_currency(): void
    {
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->create();
        $application = VendorApplication::factory()->for($applicant)->create([
            'store_name' => 'Currency Store',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.vendor-applications.approve', $application))
            ->assertRedirect();

        $this->assertSame('SYP', $applicant->fresh()->vendor->store->default_currency_code);
    }

    public function test_vendor_can_update_own_store_default_currency_to_usd(): void
    {
        $user = $this->createVendorUser();

        $this->actingAs($user)
            ->put(route('vendor.store.update'), [
                'name' => $user->vendor->store->name,
                'description' => 'Updated',
                'contact_email' => $user->email,
                'contact_phone' => $user->phone,
                'default_currency_code' => 'usd',
            ])
            ->assertRedirect(route('vendor.store'));

        $this->assertSame('USD', $user->vendor->store->fresh()->default_currency_code);
    }

    public function test_vendor_cannot_update_another_vendors_store_currency_via_policy(): void
    {
        $owner = $this->createVendorUser();
        $other = $this->createVendorUser();

        $this->assertFalse($other->can('update', $owner->vendor->store));
    }

    public function test_customer_cannot_update_store_currency(): void
    {
        $customer = User::factory()->create();
        $vendor = $this->createVendorUser();

        $this->actingAs($customer)
            ->put(route('vendor.store.update'), [
                'name' => $vendor->vendor->store->name,
                'default_currency_code' => 'USD',
            ])
            ->assertForbidden();
    }

    public function test_unsupported_currency_is_rejected(): void
    {
        $user = $this->createVendorUser();

        $this->actingAs($user)
            ->put(route('vendor.store.update'), [
                'name' => $user->vendor->store->name,
                'default_currency_code' => 'EUR',
            ])
            ->assertSessionHasErrors('default_currency_code');

        $this->assertSame('SYP', $user->vendor->store->fresh()->default_currency_code);
    }

    public function test_inactive_currency_is_rejected(): void
    {
        Currency::query()->create([
            'code' => 'XXX',
            'exponent' => 2,
            'is_active' => false,
        ]);

        $user = $this->createVendorUser();

        $this->actingAs($user)
            ->put(route('vendor.store.update'), [
                'name' => $user->vendor->store->name,
                'default_currency_code' => 'XXX',
            ])
            ->assertSessionHasErrors('default_currency_code');
    }

    public function test_store_profile_form_still_renders_with_currency_options(): void
    {
        $user = $this->createVendorUser();

        $this->actingAs($user)
            ->get(route('vendor.store'))
            ->assertOk()
            ->assertSee(__('Default currency'), false)
            ->assertSee(__('Syrian Pound (SYP)'), false)
            ->assertSee(__('US Dollar (USD)'), false);
    }

    public function test_catalog_hub_requires_brand_view_any_as_well_as_category(): void
    {
        $admin = User::factory()->admin()->create();

        Gate::before(function ($user, string $ability, array $arguments) {
            if ($ability === 'viewAny' && ($arguments[0] ?? null) === Brand::class) {
                return false;
            }

            return null;
        });

        $this->actingAs($admin)
            ->get(route('admin.catalog'))
            ->assertForbidden();
    }

    public function test_slice_does_not_introduce_exchange_rates_table(): void
    {
        $this->assertFalse(Schema::hasTable('exchange_rates'));
        $this->assertTrue(Schema::hasTable('currencies'));
        $this->assertTrue(Schema::hasColumn('stores', 'default_currency_code'));
    }
}
