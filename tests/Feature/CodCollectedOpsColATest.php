<?php

namespace Tests\Feature;

use App\Enums\ParentOrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\VendorOrderStatus;
use App\Models\OrderItem;
use App\Models\ParentOrder;
use App\Models\Payment;
use App\Models\User;
use App\Models\VendorOrder;
use App\Services\VendorOrderLifecycleService;
use App\Support\Locale;
use Database\Seeders\CommissionSettingSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\SyriaGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodCollectedOpsColATest extends TestCase
{
    use RefreshDatabase;

    private VendorOrderLifecycleService $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            SyriaGeoSeeder::class,
            CommissionSettingSeeder::class,
        ]);

        $this->lifecycle = app(VendorOrderLifecycleService::class);
    }

    public function test_guest_is_redirected_from_collect_routes(): void
    {
        [$payment, $vendorOrder] = $this->deliveredOrderWithPendingPayment();

        $this->post(route('admin.payments.collect', $payment))->assertRedirect(route('login'));
        $this->post(route('vendor.orders.collect-payment', $vendorOrder))->assertRedirect(route('login'));
    }

    public function test_staff_collect_happy_path(): void
    {
        $admin = User::factory()->admin()->create(['preferred_locale' => 'en']);
        [$payment, $vendorOrder] = $this->deliveredOrderWithPendingPayment();

        $this->actingAs($admin)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('admin.payments.show', $payment))
            ->assertOk()
            ->assertSeeText('Mark collected')
            ->assertSeeText('COD pending');

        $this->actingAs($admin)
            ->post(route('admin.payments.collect', $payment))
            ->assertRedirect(route('admin.payments.show', $payment))
            ->assertSessionHas('status', __('Payment marked as collected.'));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Collected, $payment->status);
        $this->assertNotNull($payment->collected_at);

        $this->actingAs($admin)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('admin.payments.show', $payment))
            ->assertOk()
            ->assertSeeText('Collected')
            ->assertDontSeeText('Mark collected');

        $this->assertSame($vendorOrder->id, $payment->vendor_order_id);
    }

    public function test_vendor_collect_own_order_happy_path(): void
    {
        [$owner, , $vendorOrder, $payment] = $this->deliveredGraph();

        $this->actingAs($owner)
            ->get(route('vendor.orders.show', $vendorOrder))
            ->assertOk()
            ->assertSee(__('Mark collected'), false);

        $this->actingAs($owner)
            ->post(route('vendor.orders.collect-payment', $vendorOrder))
            ->assertRedirect(route('vendor.orders.show', $vendorOrder))
            ->assertSessionHas('status', __('Payment marked as collected.'));

        $this->assertSame(PaymentStatus::Collected, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->collected_at);
    }

    public function test_collect_is_rejected_when_vendor_order_is_not_delivered(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = $this->createVendorUser();
        $customer = User::factory()->create();

        $parent = ParentOrder::factory()->for($customer)->create([
            'status' => ParentOrderStatus::Placed,
        ]);

        $vendorOrder = VendorOrder::factory()
            ->forStore($owner->vendor->store)
            ->for($parent)
            ->create([
                'status' => VendorOrderStatus::Confirmed,
            ]);

        $payment = Payment::factory()->for($vendorOrder)->create([
            'method' => PaymentMethod::Cod,
            'status' => PaymentStatus::Pending,
            'amount_minor' => $vendorOrder->grand_total_amount_minor,
            'currency_code' => $vendorOrder->currency_code,
        ]);

        $this->actingAs($admin)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('admin.payments.show', $payment))
            ->assertOk()
            ->assertDontSeeText('Mark collected');

        $this->actingAs($admin)
            ->post(route('admin.payments.collect', $payment))
            ->assertForbidden();

        $this->actingAs($owner)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('vendor.orders.show', $vendorOrder))
            ->assertOk()
            ->assertDontSeeText('Mark collected');

        $this->actingAs($owner)
            ->post(route('vendor.orders.collect-payment', $vendorOrder))
            ->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->collected_at);
    }

    public function test_collect_is_rejected_when_payment_already_collected_or_cancelled(): void
    {
        $admin = User::factory()->admin()->create();
        [$collectedPayment] = $this->deliveredOrderWithPendingPayment();
        $collectedPayment->forceFill([
            'status' => PaymentStatus::Collected,
            'collected_at' => now(),
        ])->save();

        $this->actingAs($admin)
            ->post(route('admin.payments.collect', $collectedPayment))
            ->assertForbidden();

        [$cancelledPayment, $cancelledOrder] = $this->deliveredOrderWithPendingPayment();
        $cancelledPayment->forceFill(['status' => PaymentStatus::Cancelled])->save();
        $cancelledOrder->forceFill(['status' => VendorOrderStatus::Cancelled])->save();

        $this->actingAs($admin)
            ->post(route('admin.payments.collect', $cancelledPayment))
            ->assertForbidden();
    }

    public function test_foreign_vendor_and_customer_receive_not_found_on_vendor_collect(): void
    {
        [$owner, $customer, $vendorOrder] = $this->deliveredGraph();
        $vendorOrder->loadMissing('payment');
        $stranger = $this->createVendorUser();

        $this->actingAs($stranger)
            ->post(route('vendor.orders.collect-payment', $vendorOrder))
            ->assertNotFound();

        $this->actingAs($customer)
            ->post(route('vendor.orders.collect-payment', $vendorOrder))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->get(route('vendor.orders.show', $vendorOrder))
            ->assertNotFound();

        $this->assertTrue($owner->can('collect', $vendorOrder->payment));
        $this->assertFalse($stranger->can('collect', $vendorOrder->payment));
        $this->assertFalse($customer->can('collect', $vendorOrder->payment));
    }

    public function test_non_staff_cannot_collect_from_admin_route(): void
    {
        [$owner, , , $payment] = $this->deliveredGraph();
        $customer = User::factory()->create();

        $this->actingAs($owner)->post(route('admin.payments.collect', $payment))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.payments.collect', $payment))->assertForbidden();
    }

    public function test_deliver_leaves_payment_pending_until_manual_collect(): void
    {
        [$owner, , $order] = $this->pendingGraph();

        $confirmed = $this->lifecycle->confirm($owner, $order);
        $shipped = $this->lifecycle->ship($owner, $confirmed);
        $delivered = $this->lifecycle->deliver($owner, $shipped);

        $this->assertSame(VendorOrderStatus::Delivered, $delivered->status);
        $this->assertSame(PaymentStatus::Pending, $delivered->payment?->fresh()->status);
        $this->assertNull($delivered->payment?->fresh()->collected_at);
    }

    /**
     * @return array{0: Payment, 1: VendorOrder}
     */
    private function deliveredOrderWithPendingPayment(): array
    {
        [, , $vendorOrder, $payment] = $this->deliveredGraph();

        return [$payment, $vendorOrder];
    }

    /**
     * @return array{0: User, 1: User, 2: VendorOrder, 3: Payment}
     */
    private function deliveredGraph(): array
    {
        [$owner, $customer, $order] = $this->pendingGraph();

        $confirmed = $this->lifecycle->confirm($owner, $order);
        $shipped = $this->lifecycle->ship($owner, $confirmed);
        $delivered = $this->lifecycle->deliver($owner, $shipped)->fresh(['payment']);

        return [
            $owner,
            $customer,
            $delivered,
            $delivered->payment,
        ];
    }

    /**
     * @return array{0: User, 1: User, 2: VendorOrder}
     */
    private function pendingGraph(): array
    {
        $owner = $this->createVendorUser();
        $customer = User::factory()->create();

        $parent = ParentOrder::factory()->for($customer)->create([
            'status' => ParentOrderStatus::Placed,
        ]);

        $vendorOrder = VendorOrder::factory()
            ->forStore($owner->vendor->store)
            ->for($parent)
            ->create([
                'status' => VendorOrderStatus::Pending,
                'commission_recognized_at' => null,
            ]);

        OrderItem::factory()->for($vendorOrder)->create([
            'currency_code' => $vendorOrder->currency_code,
            'quantity' => 1,
            'unit_price_amount_minor' => 5_000,
            'line_total_amount_minor' => 5_000,
            'store_name' => $owner->vendor->store->name,
            'vendor_id' => $owner->vendor->id,
            'store_id' => $owner->vendor->store->id,
        ]);

        Payment::factory()->for($vendorOrder)->create([
            'method' => PaymentMethod::Cod,
            'status' => PaymentStatus::Pending,
            'amount_minor' => $vendorOrder->grand_total_amount_minor,
            'currency_code' => $vendorOrder->currency_code,
        ]);

        return [
            $owner->fresh(['vendor.store', 'roles']),
            $customer->fresh(),
            $vendorOrder->fresh(['payment', 'parentOrder', 'vendor.user']),
        ];
    }
}
