<?php

namespace Tests\Feature;

use App\Enums\ParentOrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\VendorOrderStatus;
use App\Models\ParentOrder;
use App\Models\Payment;
use App\Models\User;
use App\Models\VendorOrder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\SyriaGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VendorOrderLifecycleVolBTest extends TestCase
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

    public function test_owner_can_confirm_ship_and_deliver_via_show_actions(): void
    {
        Notification::fake();

        [$owner, , $order] = $this->pendingOrderOwnedByVendor();

        $this->actingAs($owner)
            ->get(route('vendor.orders.show', $order))
            ->assertOk()
            ->assertSee('data-advance-action', false)
            ->assertSee(__('Confirm'), false);

        $this->actingAs($owner)
            ->from(route('vendor.orders.show', $order))
            ->post(route('vendor.orders.advance', $order), [
                'status' => VendorOrderStatus::Confirmed->value,
            ])
            ->assertRedirect(route('vendor.orders.show', $order))
            ->assertSessionHas('status', __('Order confirmed.'));

        $this->assertSame(VendorOrderStatus::Confirmed, $order->fresh()->status);

        $this->actingAs($owner)
            ->get(route('vendor.orders.show', $order->fresh()))
            ->assertOk()
            ->assertSee(__('Mark shipped'), false)
            ->assertSee(__('Confirmed'), false);

        $this->actingAs($owner)
            ->post(route('vendor.orders.advance', $order), [
                'status' => VendorOrderStatus::Shipped->value,
            ])
            ->assertRedirect(route('vendor.orders.show', $order))
            ->assertSessionHas('status', __('Order marked as shipped.'));

        $this->assertSame(VendorOrderStatus::Shipped, $order->fresh()->status);

        $this->actingAs($owner)
            ->post(route('vendor.orders.advance', $order), [
                'status' => VendorOrderStatus::Delivered->value,
            ])
            ->assertRedirect(route('vendor.orders.show', $order))
            ->assertSessionHas('status', __('Order marked as delivered.'));

        $delivered = $order->fresh();
        $this->assertSame(VendorOrderStatus::Delivered, $delivered->status);
        $this->assertNotNull($delivered->commission_recognized_at);

        $this->actingAs($owner)
            ->get(route('vendor.orders.show', $delivered))
            ->assertOk()
            ->assertDontSee('data-advance-action')
            ->assertSee(__('Delivered'), false);
    }

    public function test_non_owner_advance_is_not_found(): void
    {
        Notification::fake();

        [, , $order] = $this->pendingOrderOwnedByVendor();
        $stranger = $this->createVendorUser();

        $this->actingAs($stranger)
            ->get(route('vendor.orders.show', $order))
            ->assertNotFound();

        $this->actingAs($stranger)
            ->post(route('vendor.orders.advance', $order), [
                'status' => VendorOrderStatus::Confirmed->value,
            ])
            ->assertNotFound();

        $this->assertSame(VendorOrderStatus::Pending, $order->fresh()->status);
    }

    public function test_illegal_transition_is_rejected_and_status_unchanged(): void
    {
        Notification::fake();

        [$owner, , $order] = $this->pendingOrderOwnedByVendor();

        $this->actingAs($owner)
            ->from(route('vendor.orders.show', $order))
            ->post(route('vendor.orders.advance', $order), [
                'status' => VendorOrderStatus::Shipped->value,
            ])
            ->assertRedirect(route('vendor.orders.show', $order))
            ->assertSessionHasErrors('status');

        $this->assertSame(VendorOrderStatus::Pending, $order->fresh()->status);

        $this->actingAs($owner)
            ->from(route('vendor.orders.show', $order))
            ->post(route('vendor.orders.advance', $order), [
                'status' => 'cancelled',
            ])
            ->assertRedirect(route('vendor.orders.show', $order))
            ->assertSessionHasErrors('status');

        $this->assertSame(VendorOrderStatus::Pending, $order->fresh()->status);
    }

    public function test_customer_cannot_advance_vendor_order(): void
    {
        Notification::fake();

        [, $customer, $order] = $this->pendingOrderOwnedByVendor();

        $this->actingAs($customer)
            ->post(route('vendor.orders.advance', $order), [
                'status' => VendorOrderStatus::Confirmed->value,
            ])
            ->assertForbidden();

        $this->assertSame(VendorOrderStatus::Pending, $order->fresh()->status);
    }

    /**
     * @return array{0: User, 1: User, 2: VendorOrder}
     */
    private function pendingOrderOwnedByVendor(): array
    {
        $owner = $this->createVendorUser();
        $customer = User::factory()->create();

        $parent = ParentOrder::factory()->for($customer)->create([
            'status' => ParentOrderStatus::Placed,
        ]);

        $order = VendorOrder::factory()
            ->forStore($owner->vendor->store)
            ->for($parent)
            ->create([
                'status' => VendorOrderStatus::Pending,
                'commission_recognized_at' => null,
            ]);

        Payment::factory()->for($order)->create([
            'status' => PaymentStatus::Pending,
            'amount_minor' => $order->grand_total_amount_minor,
            'currency_code' => $order->currency_code,
        ]);

        return [
            $owner->fresh(['vendor', 'roles']),
            $customer->fresh(),
            $order->fresh(['payment', 'parentOrder', 'vendor.user']),
        ];
    }
}
