<?php

namespace Tests\Feature;

use App\Models\ParentOrder;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorOrder;
use App\Notifications\OrderPlacedCustomerNotification;
use App\Notifications\VendorApplicationApprovedNotification;
use App\Notifications\VendorOrderReceivedNotification;
use App\Support\Locale;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\SyriaGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalPolishProCTest extends TestCase
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

    public function test_guest_cannot_mark_notifications_read(): void
    {
        $this->post(route('account.notifications.read-all'))
            ->assertRedirect(route('login'));

        $this->post(route('account.notifications.read', '00000000-0000-0000-0000-000000000001'))
            ->assertRedirect(route('login'));
    }

    public function test_customer_sees_database_notification_in_tray(): void
    {
        $customer = User::factory()->create(['preferred_locale' => 'en']);
        $parentOrder = ParentOrder::factory()->for($customer)->create();

        $customer->notify(new OrderPlacedCustomerNotification($parentOrder, []));

        $this->actingAs($customer)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Your order :code was placed successfully.', [
                'code' => $parentOrder->public_code,
            ]), false)
            ->assertSee(route('account.orders.show', $parentOrder), false)
            ->assertSee(__('Mark as read'), false)
            ->assertDontSee(__('No notifications yet. This tray is a visual placeholder.'), false)
            ->assertDontSee(__('No notifications yet.'), false);
    }

    public function test_vendor_sees_database_notification_in_tray(): void
    {
        $vendorUser = $this->createVendorUser(['preferred_locale' => 'en']);
        $customer = User::factory()->create();
        $parentOrder = ParentOrder::factory()->for($customer)->create();
        $vendorOrder = VendorOrder::factory()
            ->forStore($vendorUser->vendor->store)
            ->for($parentOrder)
            ->create();

        $vendorUser->notify(new VendorOrderReceivedNotification($vendorOrder, '150.00 SYP'));

        $this->actingAs($vendorUser)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('vendor.dashboard'))
            ->assertOk()
            ->assertSee(__('You received a new order :code.', [
                'code' => $vendorOrder->public_code,
            ]), false)
            ->assertSee(route('vendor.orders.show', $vendorOrder), false)
            ->assertDontSee(__('No notifications yet. This tray is a visual placeholder.'), false);
    }

    public function test_customer_can_mark_single_notification_read(): void
    {
        $customer = User::factory()->create(['preferred_locale' => 'en']);
        $parentOrder = ParentOrder::factory()->for($customer)->create();

        $customer->notify(new OrderPlacedCustomerNotification($parentOrder, []));

        $notification = $customer->fresh()->notifications()->firstOrFail();
        $this->assertNull($notification->read_at);

        $this->actingAs($customer)
            ->from(route('dashboard'))
            ->post(route('account.notifications.read', $notification->id))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', __('Notification marked as read.'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_customer_can_mark_all_notifications_read(): void
    {
        $customer = User::factory()->create(['preferred_locale' => 'en']);
        $firstOrder = ParentOrder::factory()->for($customer)->create();
        $secondOrder = ParentOrder::factory()->for($customer)->create();

        $customer->notify(new OrderPlacedCustomerNotification($firstOrder, []));
        $customer->notify(new OrderPlacedCustomerNotification($secondOrder, []));

        $this->assertSame(2, $customer->fresh()->unreadNotifications()->count());

        $this->actingAs($customer)
            ->from(route('dashboard'))
            ->post(route('account.notifications.read-all'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status', __('All notifications marked as read.'));

        $this->assertSame(0, $customer->fresh()->unreadNotifications()->count());
    }

    public function test_empty_tray_shows_live_empty_state(): void
    {
        $customer = User::factory()->create(['preferred_locale' => 'en']);

        $this->actingAs($customer)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('No notifications yet.'), false)
            ->assertDontSee(__('No notifications yet. This tray is a visual placeholder.'), false);
    }

    public function test_user_cannot_mark_another_users_notification_read(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $parentOrder = ParentOrder::factory()->for($owner)->create();

        $owner->notify(new OrderPlacedCustomerNotification($parentOrder, []));
        $notificationId = $owner->fresh()->notifications()->firstOrFail()->id;

        $this->actingAs($stranger)
            ->post(route('account.notifications.read', $notificationId))
            ->assertNotFound();
    }

    public function test_vendor_application_approved_notification_appears_in_tray(): void
    {
        $applicant = User::factory()->create(['preferred_locale' => 'en']);
        $application = VendorApplication::factory()->for($applicant)->create([
            'store_name' => 'Tray Store',
        ]);

        $applicant->notify(new VendorApplicationApprovedNotification($application));

        $this->actingAs($applicant)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Your store :name is ready. You can now open the seller workspace.', [
                'name' => 'Tray Store',
            ]), false)
            ->assertSee(route('vendor.dashboard'), false);
    }
}
