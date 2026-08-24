<?php

namespace Tests\Feature;

use App\Enums\ParentOrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\VendorOrderStatus;
use App\Models\ParentOrder;
use App\Models\Payment;
use App\Models\User;
use App\Models\VendorOrder;
use App\Services\VendorOrderLifecycleService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\SyriaGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VendorOrderLifecycleVolCTest extends TestCase
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

    public function test_customer_parent_show_and_index_reflect_live_vendor_order_statuses(): void
    {
        Notification::fake();

        [$owner, $customer, $order] = $this->pendingOrderOwnedByVendor();
        $parent = $order->parentOrder;
        $lifecycle = app(VendorOrderLifecycleService::class);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $parent))
            ->assertOk()
            ->assertSee(__('Pending'), false)
            ->assertSee(__('Shipment status'), false)
            ->assertSee('data-vendor-shipment-status', false)
            ->assertDontSee('data-advance-action')
            ->assertDontSee(__('Mark shipped'), false)
            ->assertDontSee(route('vendor.orders.advance', $order), false);

        $this->actingAs($customer)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertSee(__('Shipments'), false)
            ->assertSee($order->public_code, false)
            ->assertSee(__('Pending'), false)
            ->assertDontSee('data-advance-action');

        $lifecycle->confirm($owner, $order);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $parent))
            ->assertOk()
            ->assertSee(__('Confirmed'), false)
            ->assertDontSee(__('Mark shipped'), false);

        $this->actingAs($customer)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertSee(__('Confirmed'), false);

        $lifecycle->ship($owner, $order->fresh());

        $this->actingAs($customer)
            ->get(route('account.orders.show', $parent))
            ->assertOk()
            ->assertSee(__('Shipped'), false);

        $this->actingAs($customer)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertSee(__('Shipped'), false);

        $lifecycle->deliver($owner, $order->fresh());

        $this->actingAs($customer)
            ->get(route('account.orders.show', $parent))
            ->assertOk()
            ->assertSee(__('Delivered'), false)
            ->assertSee(__('Placed'), false)
            ->assertDontSee('data-advance-action')
            ->assertDontSee(__('Mark shipped'), false);

        $this->actingAs($customer)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertSee(__('Delivered'), false)
            ->assertSee(__('Placed'), false)
            ->assertDontSee('data-advance-action');
    }

    public function test_customer_cannot_post_vendor_advance_and_has_no_transition_controls(): void
    {
        Notification::fake();

        [, $customer, $order] = $this->pendingOrderOwnedByVendor();
        $parent = $order->parentOrder;

        $this->actingAs($customer)
            ->post(route('vendor.orders.advance', $order), [
                'status' => VendorOrderStatus::Confirmed->value,
            ])
            ->assertForbidden();

        $this->assertSame(VendorOrderStatus::Pending, $order->fresh()->status);

        $show = $this->actingAs($customer)
            ->get(route('account.orders.show', $parent))
            ->assertOk();

        $show->assertDontSee('name="status"', false);
        $show->assertDontSee(route('vendor.orders.advance', $order), false);
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
