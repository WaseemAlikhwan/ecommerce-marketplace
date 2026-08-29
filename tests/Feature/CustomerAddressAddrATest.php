<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\CustomerAddress;
use App\Models\Governorate;
use App\Models\Role;
use App\Models\User;
use App\Services\CustomerAddressService;
use Database\Seeders\SyriaGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAddressAddrATest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SyriaGeoSeeder::class);
    }

    public function test_guest_is_redirected_from_address_book(): void
    {
        $this->get(route('account.addresses'))->assertRedirect('/login');
        $this->get(route('account.addresses.create'))->assertRedirect('/login');
        $this->post(route('account.addresses.store'))->assertRedirect('/login');
    }

    public function test_non_customer_is_not_found_on_address_book(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->roles()->detach();
        $admin->assignRole(Role::ADMIN);

        $this->actingAs($admin)
            ->get(route('account.addresses'))
            ->assertNotFound();
    }

    public function test_customer_can_crud_addresses(): void
    {
        $customer = User::factory()->create(['preferred_locale' => 'en']);
        [$governorate, $city] = $this->activeGeo();

        $this->actingAs($customer)
            ->get(route('account.addresses.create'))
            ->assertOk()
            ->assertSee('Add address', false)
            ->assertSee('Select governorate', false);

        $this->actingAs($customer)
            ->post(route('account.addresses.store'), $this->payload($governorate, $city, [
                'label' => 'Home',
                'recipient_name' => 'Sam Customer',
                'phone' => '+963911111111',
                'line1' => 'Main Street 1',
                'is_default' => '1',
            ]))
            ->assertRedirect(route('account.addresses'))
            ->assertSessionHas('status', __('Address saved.'));

        $address = CustomerAddress::query()->where('user_id', $customer->id)->first();
        $this->assertNotNull($address);
        $this->assertTrue($address->is_default);

        $this->actingAs($customer)
            ->get(route('account.addresses'))
            ->assertOk()
            ->assertSee('Home', false)
            ->assertSee('Sam Customer', false)
            ->assertSee('Default', false);

        $this->actingAs($customer)
            ->get(route('account.addresses.edit', $address))
            ->assertOk()
            ->assertSee('Edit address', false);

        $this->actingAs($customer)
            ->put(route('account.addresses.update', $address), $this->payload($governorate, $city, [
                'label' => 'Work',
                'recipient_name' => 'Sam Customer',
                'phone' => '+963922222222',
                'line1' => 'Office Road 2',
            ]))
            ->assertRedirect(route('account.addresses'))
            ->assertSessionHas('status', __('Address updated.'));

        $this->assertDatabaseHas('customer_addresses', [
            'id' => $address->id,
            'label' => 'Work',
            'line1' => 'Office Road 2',
        ]);

        $this->actingAs($customer)
            ->delete(route('account.addresses.destroy', $address))
            ->assertRedirect(route('account.addresses'))
            ->assertSessionHas('status', __('Address deleted.'));

        $this->assertDatabaseMissing('customer_addresses', ['id' => $address->id]);
    }

    public function test_set_default_clears_previous_default(): void
    {
        $customer = User::factory()->create();
        [$governorate, $city] = $this->activeGeo();

        $first = CustomerAddress::factory()->for($customer)->default()->create([
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
        ]);
        $second = CustomerAddress::factory()->for($customer)->create([
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
            'is_default' => false,
        ]);

        $this->actingAs($customer)
            ->post(route('account.addresses.default', $second))
            ->assertRedirect(route('account.addresses'))
            ->assertSessionHas('status', __('Default address updated.'));

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_service_update_can_set_default(): void
    {
        $customer = User::factory()->create();
        [$governorate, $city] = $this->activeGeo();
        $address = CustomerAddress::factory()->for($customer)->create([
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
            'is_default' => false,
        ]);

        app(CustomerAddressService::class)->update($address, [
            'label' => 'Home',
            'recipient_name' => 'Sam',
            'phone' => '+963911111111',
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
            'line1' => 'Street',
            'line2' => null,
            'notes' => null,
            'is_default' => true,
        ]);

        $this->assertTrue($address->fresh()->is_default);
    }

    public function test_city_governorate_mismatch_is_rejected(): void
    {
        $customer = User::factory()->create();
        [$govA, $cityA] = $this->activeGeo();
        [$govB] = $this->secondGovernorate($govA);

        $this->actingAs($customer)
            ->from(route('account.addresses.create'))
            ->post(route('account.addresses.store'), $this->payload($govB, $cityA, [
                'recipient_name' => 'Mismatch',
                'phone' => '+963933333333',
                'line1' => 'Wrong city',
            ]))
            ->assertRedirect(route('account.addresses.create'))
            ->assertSessionHasErrors('city_id');

        $this->assertDatabaseCount('customer_addresses', 0);
    }

    public function test_inactive_governorate_is_rejected(): void
    {
        $customer = User::factory()->create();
        [$governorate, $city] = $this->activeGeo();
        $governorate->update(['is_active' => false]);

        $this->actingAs($customer)
            ->from(route('account.addresses.create'))
            ->post(route('account.addresses.store'), $this->payload($governorate, $city, [
                'recipient_name' => 'Inactive Gov',
                'phone' => '+963944444444',
                'line1' => 'Blocked',
            ]))
            ->assertRedirect(route('account.addresses.create'))
            ->assertSessionHasErrors('governorate_id');
    }

    public function test_stranger_cannot_mutate_foreign_address(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        [$governorate, $city] = $this->activeGeo();
        $address = CustomerAddress::factory()->for($owner)->create([
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
        ]);

        $this->actingAs($stranger)
            ->get(route('account.addresses.edit', $address))
            ->assertNotFound();

        $this->actingAs($stranger)
            ->put(route('account.addresses.update', $address), $this->payload($governorate, $city, [
                'recipient_name' => 'Hacked',
                'phone' => '+963955555555',
                'line1' => 'No access',
            ]))
            ->assertNotFound();

        $this->actingAs($stranger)
            ->delete(route('account.addresses.destroy', $address))
            ->assertNotFound();

        $this->actingAs($stranger)
            ->post(route('account.addresses.default', $address))
            ->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Governorate $governorate, City $city, array $overrides = []): array
    {
        return array_merge([
            'label' => null,
            'recipient_name' => 'Recipient',
            'phone' => '+963900000001',
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
            'line1' => 'Line 1',
            'line2' => null,
            'notes' => null,
        ], $overrides);
    }

    /**
     * @return array{0: Governorate, 1: City}
     */
    private function activeGeo(): array
    {
        $governorate = Governorate::query()->inSyria()->active()->orderBy('id')->firstOrFail();
        $city = $governorate->cities()->where('is_active', true)->orderBy('id')->firstOrFail();

        return [$governorate, $city];
    }

    /**
     * @return array{0: Governorate, 1: City}
     */
    private function secondGovernorate(Governorate $exclude): array
    {
        $governorate = Governorate::query()
            ->inSyria()
            ->active()
            ->whereKeyNot($exclude->id)
            ->orderBy('id')
            ->firstOrFail();
        $city = $governorate->cities()->where('is_active', true)->orderBy('id')->firstOrFail();

        return [$governorate, $city];
    }
}
