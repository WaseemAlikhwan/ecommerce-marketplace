<?php

namespace Tests\Feature;

use App\Enums\ParentOrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\VendorOrderStatus;
use App\Models\ParentOrder;
use App\Models\Payment;
use App\Models\User;
use App\Models\VendorOrder;
use App\Support\Locale;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\SyriaGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalPolishProBTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            SyriaGeoSeeder::class,
        ]);
    }

    public function test_storefront_home_shows_live_trust_strip_without_later_phase_copy(): void
    {
        $this->withCookie(Locale::COOKIE, 'en')
            ->get(route('home'))
            ->assertOk()
            ->assertSee(__('Pay with cash on delivery (COD).'), false)
            ->assertSee(__('Delivery across Syrian cities.'), false)
            ->assertSee(__('Independent Syrian stores'), false)
            ->assertDontSee(__('Payment stays in a later phase'), false)
            ->assertDontSee(__('City delivery when commerce opens'), false);
    }

    public function test_guest_is_redirected_from_vendor_dashboard(): void
    {
        $this->get(route('vendor.dashboard'))->assertRedirect(route('login'));
    }

    public function test_vendor_dashboard_shows_order_kpis_and_live_copy(): void
    {
        $owner = $this->createVendorUser(['preferred_locale' => 'en']);
        $customer = User::factory()->create();
        $stranger = $this->createVendorUser();

        $pendingParent = ParentOrder::factory()->for($customer)->create([
            'status' => ParentOrderStatus::Placed,
        ]);

        $pending = VendorOrder::factory()
            ->forStore($owner->vendor->store)
            ->for($pendingParent)
            ->create(['status' => VendorOrderStatus::Pending]);

        Payment::factory()->for($pending)->create([
            'method' => PaymentMethod::Cod,
            'status' => PaymentStatus::Pending,
            'amount_minor' => $pending->grand_total_amount_minor,
            'currency_code' => $pending->currency_code,
        ]);

        $deliveredParent = ParentOrder::factory()->for($customer)->create([
            'status' => ParentOrderStatus::Placed,
        ]);

        $delivered = VendorOrder::factory()
            ->forStore($owner->vendor->store)
            ->for($deliveredParent)
            ->create(['status' => VendorOrderStatus::Delivered]);

        Payment::factory()->for($delivered)->create([
            'method' => PaymentMethod::Cod,
            'status' => PaymentStatus::Pending,
            'amount_minor' => $delivered->grand_total_amount_minor,
            'currency_code' => $delivered->currency_code,
        ]);

        $confirmedParent = ParentOrder::factory()->for($customer)->create([
            'status' => ParentOrderStatus::Placed,
        ]);

        VendorOrder::factory()
            ->forStore($owner->vendor->store)
            ->for($confirmedParent)
            ->create(['status' => VendorOrderStatus::Confirmed]);

        $strangerPendingParent = ParentOrder::factory()->for($customer)->create([
            'status' => ParentOrderStatus::Placed,
        ]);

        VendorOrder::factory()
            ->forStore($stranger->vendor->store)
            ->for($strangerPendingParent)
            ->create(['status' => VendorOrderStatus::Pending]);

        $strangerDeliveredParent = ParentOrder::factory()->for($customer)->create([
            'status' => ParentOrderStatus::Placed,
        ]);

        VendorOrder::factory()
            ->forStore($stranger->vendor->store)
            ->for($strangerDeliveredParent)
            ->create(['status' => VendorOrderStatus::Delivered]);

        $response = $this->actingAs($owner)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('vendor.dashboard'));

        $response->assertOk();
        $response->assertSee(__('Manage your catalog, fulfill vendor orders, and track COD collections.'), false);
        $response->assertSee(__('Pending orders'), false);
        $response->assertSee(__('Delivered orders'), false);
        $response->assertSee(route('vendor.orders'), false);
        $response->assertSee(__('View orders'), false);
        $response->assertDontSee(__('Your store is live for identity setup. Catalog and orders arrive in later phases.'), false);
        $response->assertDontSee('later phases', false);

        $html = $response->getContent();
        $this->assertMatchesRegularExpression(
            '/Pending orders<\/p>\s*<p class="mt-2 font-display text-heading-2">1<\/p>/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/Delivered orders<\/p>\s*<p class="mt-2 font-display text-heading-2">1<\/p>/',
            $html,
        );
    }
}
