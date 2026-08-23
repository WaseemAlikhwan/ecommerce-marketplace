<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\VendorApplicationStatus;
use App\Enums\VendorStatus;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorApplication;
use App\Notifications\VendorApplicationApprovedNotification;
use App\Notifications\VendorApplicationRejectedNotification;
use App\Notifications\VendorApplicationSubmittedNotification;
use App\Services\VendorApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VendorOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_submit_vendor_application(): void
    {
        $this->post('/account/vendor-application', [
            'store_name' => 'Beit Test',
        ])->assertRedirect('/login');

        $this->assertDatabaseCount('vendor_applications', 0);
    }

    public function test_unverified_user_cannot_submit_vendor_application(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post('/account/vendor-application', ['store_name' => 'Beit Test'])
            ->assertForbidden();

        $this->assertDatabaseCount('vendor_applications', 0);
    }

    public function test_verified_customer_can_submit_vendor_application(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/account/vendor-application', [
                'store_name' => 'Beit Test',
                'note' => 'Local home goods',
            ])
            ->assertRedirect(route('account.vendor-application'));

        $this->assertDatabaseHas('vendor_applications', [
            'user_id' => $user->id,
            'store_name' => 'Beit Test',
            'status' => VendorApplicationStatus::Pending->value,
            'pending_for_user_id' => $user->id,
        ]);

        Notification::assertSentTo($admin, VendorApplicationSubmittedNotification::class);
    }

    public function test_duplicate_pending_application_is_prevented(): void
    {
        $user = User::factory()->create();
        VendorApplication::factory()->for($user)->pending()->create();

        $this->actingAs($user)
            ->post('/account/vendor-application', ['store_name' => 'Second Store'])
            ->assertSessionHasErrors('store_name');

        $this->assertSame(1, $user->vendorApplications()->count());
    }

    public function test_admin_can_view_pending_applications(): void
    {
        $admin = User::factory()->admin()->create();
        $application = VendorApplication::factory()->create(['store_name' => 'Pending Store']);

        $this->actingAs($admin)
            ->get('/admin/vendors')
            ->assertOk()
            ->assertSee('Pending Store', false);

        $this->actingAs($admin)
            ->get(route('admin.vendor-applications.show', $application))
            ->assertOk()
            ->assertSee('Pending Store', false);
    }

    public function test_customer_cannot_approve_or_reject_applications(): void
    {
        $customer = User::factory()->create();
        $application = VendorApplication::factory()->create();

        $this->actingAs($customer)
            ->post(route('admin.vendor-applications.approve', $application))
            ->assertForbidden();

        $this->actingAs($customer)
            ->post(route('admin.vendor-applications.reject', $application))
            ->assertForbidden();

        $this->assertTrue($application->fresh()->isPending());
    }

    public function test_vendor_cannot_approve_or_reject_applications(): void
    {
        $vendorUser = $this->createVendorUser();
        $application = VendorApplication::factory()->create();

        $this->actingAs($vendorUser)
            ->post(route('admin.vendor-applications.approve', $application))
            ->assertForbidden();

        $this->actingAs($vendorUser)
            ->post(route('admin.vendor-applications.reject', $application))
            ->assertForbidden();
    }

    public function test_admin_can_approve_application_and_create_one_store(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->create();
        $application = VendorApplication::factory()->for($applicant)->create([
            'store_name' => 'Souk House',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.vendor-applications.approve', $application))
            ->assertRedirect(route('admin.vendor-applications.show', $application));

        $applicant->refresh();
        $this->assertTrue($applicant->isVendor());
        $this->assertTrue($applicant->isCustomer());
        $this->assertSame(VendorApplicationStatus::Approved, $application->fresh()->status);
        $this->assertSame(1, Vendor::query()->where('user_id', $applicant->id)->count());
        $this->assertSame(1, Store::query()->where('vendor_id', $applicant->vendor->id)->count());
        $this->assertSame('Souk House', $applicant->vendor->store->name);
        $this->assertSame(VendorStatus::Approved, $applicant->vendor->status);
        $this->assertSame(StoreStatus::Active, $applicant->vendor->store->status);

        Notification::assertSentTo($applicant, VendorApplicationApprovedNotification::class);
    }

    public function test_repeated_approval_does_not_create_duplicate_stores(): void
    {
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->create();
        $application = VendorApplication::factory()->for($applicant)->create(['store_name' => 'Nur Beauty']);
        $service = app(VendorApplicationService::class);

        $service->approve($application, $admin);
        $service->approve($application->fresh(), $admin);

        $this->assertSame(1, Vendor::query()->count());
        $this->assertSame(1, Store::query()->count());
        $this->assertTrue($applicant->fresh()->isVendor());
    }

    public function test_rejection_does_not_create_store_or_grant_vendor_role(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->create();
        $application = VendorApplication::factory()->for($applicant)->create([
            'store_name' => 'Rejected Store',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.vendor-applications.reject', $application), [
                'rejection_reason' => 'Missing contact details',
            ])
            ->assertRedirect(route('admin.vendor-applications.show', $application));

        $this->assertSame(VendorApplicationStatus::Rejected, $application->fresh()->status);
        $this->assertSame('Missing contact details', $application->fresh()->rejection_reason);
        $this->assertFalse($applicant->fresh()->isVendor());
        $this->assertDatabaseCount('vendors', 0);
        $this->assertDatabaseCount('stores', 0);

        Notification::assertSentTo($applicant, VendorApplicationRejectedNotification::class);
    }

    public function test_vendor_can_access_and_update_own_store(): void
    {
        $user = $this->createVendorUser();

        $this->actingAs($user)
            ->get('/vendor')
            ->assertOk()
            ->assertSee($user->vendor->store->name, false);

        $this->actingAs($user)
            ->put(route('vendor.store.update'), [
                'name' => 'Updated Store',
                'description' => 'Damascus goods',
                'contact_email' => $user->email,
                'contact_phone' => $user->phone,
                'default_currency_code' => 'SYP',
            ])
            ->assertRedirect(route('vendor.store'));

        $this->assertSame('Updated Store', $user->vendor->store->fresh()->name);
    }

    public function test_vendor_cannot_access_another_vendors_store(): void
    {
        $owner = $this->createVendorUser();
        $other = $this->createVendorUser();

        $this->assertFalse($other->can('view', $owner->vendor->store));
        $this->assertFalse($other->can('update', $owner->vendor->store));
        $this->assertTrue($owner->can('update', $owner->vendor->store));
    }

    public function test_customer_cannot_access_vendor_or_admin_panels(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get('/vendor')->assertForbidden();
        $this->actingAs($customer)->get('/vendor/store')->assertForbidden();
        $this->actingAs($customer)->get('/admin')->assertForbidden();
        $this->actingAs($customer)->get('/admin/vendors')->assertForbidden();
    }

    public function test_super_admin_retains_administrative_access(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $application = VendorApplication::factory()->create(['store_name' => 'Admin Review']);

        $this->actingAs($superAdmin)->get('/admin')->assertOk();
        $this->actingAs($superAdmin)->get('/admin/vendors')->assertOk()->assertSee('Admin Review', false);
        $this->actingAs($superAdmin)
            ->post(route('admin.vendor-applications.approve', $application))
            ->assertRedirect();

        $this->assertTrue($application->user->fresh()->isVendor());
    }

    public function test_rejected_user_may_submit_a_new_application(): void
    {
        $user = User::factory()->create();
        VendorApplication::factory()->for($user)->rejected()->create();

        $this->actingAs($user)
            ->post('/account/vendor-application', ['store_name' => 'Second Attempt'])
            ->assertRedirect(route('account.vendor-application'));

        $this->assertSame(2, $user->vendorApplications()->count());
        $this->assertTrue($user->vendorApplications()->pending()->exists());
    }
}
