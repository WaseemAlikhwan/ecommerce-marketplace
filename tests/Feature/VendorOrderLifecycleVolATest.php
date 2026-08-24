<?php

namespace Tests\Feature;

use App\Enums\ParentOrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\VendorOrderStatus;
use App\Exceptions\VendorOrderLifecycleException;
use App\Models\ParentOrder;
use App\Models\Payment;
use App\Models\User;
use App\Models\VendorOrder;
use App\Notifications\VendorOrderStatusChangedCustomerNotification;
use App\Notifications\VendorOrderStatusChangedVendorNotification;
use App\Services\VendorOrderLifecycleService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\SyriaGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VendorOrderLifecycleVolATest extends TestCase
{
    use RefreshDatabase;

    private VendorOrderLifecycleService $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            SyriaGeoSeeder::class,
        ]);

        $this->lifecycle = app(VendorOrderLifecycleService::class);
    }

    public function test_happy_path_forward_transitions_recognize_commission_once_and_notify(): void
    {
        Notification::fake();

        [$owner, $customer, $order] = $this->pendingOrderOwnedByVendor();
        $paymentStatus = $order->payment?->status;
        $parentStatus = $order->parentOrder?->status;

        $confirmed = $this->lifecycle->confirm($owner, $order);
        $this->assertSame(VendorOrderStatus::Confirmed, $confirmed->status);
        $this->assertNull($confirmed->commission_recognized_at);

        $shipped = $this->lifecycle->ship($owner, $confirmed);
        $this->assertSame(VendorOrderStatus::Shipped, $shipped->status);
        $this->assertNull($shipped->commission_recognized_at);

        $delivered = $this->lifecycle->deliver($owner, $shipped);
        $this->assertSame(VendorOrderStatus::Delivered, $delivered->status);
        $this->assertNotNull($delivered->commission_recognized_at);

        $recognizedAt = $delivered->commission_recognized_at;
        $this->travel(5)->seconds();
        $deliveredAgain = $delivered->fresh();
        $this->assertTrue($recognizedAt->equalTo($deliveredAgain?->commission_recognized_at));

        $this->assertSame(ParentOrderStatus::Placed, $delivered->parentOrder?->fresh()->status);
        $this->assertSame($parentStatus, $delivered->parentOrder?->fresh()->status);
        $this->assertSame($paymentStatus, $delivered->payment?->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $delivered->payment?->fresh()->status);

        Notification::assertSentTo($customer, VendorOrderStatusChangedCustomerNotification::class, function ($notification) {
            return $notification->status === VendorOrderStatus::Confirmed;
        });
        Notification::assertSentTo($customer, VendorOrderStatusChangedCustomerNotification::class, function ($notification) {
            return $notification->status === VendorOrderStatus::Shipped;
        });
        Notification::assertSentTo($customer, VendorOrderStatusChangedCustomerNotification::class, function ($notification) {
            return $notification->status === VendorOrderStatus::Delivered;
        });

        Notification::assertSentTo($owner, VendorOrderStatusChangedVendorNotification::class, function ($notification) {
            return $notification->status === VendorOrderStatus::Confirmed;
        });
        Notification::assertSentTo($owner, VendorOrderStatusChangedVendorNotification::class, function ($notification) {
            return $notification->status === VendorOrderStatus::Shipped;
        });
        Notification::assertSentTo($owner, VendorOrderStatusChangedVendorNotification::class, function ($notification) {
            return $notification->status === VendorOrderStatus::Delivered;
        });
    }

    public function test_skips_and_regressions_are_rejected(): void
    {
        [$owner, , $order] = $this->pendingOrderOwnedByVendor();

        try {
            $this->lifecycle->ship($owner, $order);
            $this->fail('Expected illegal skip pending→shipped');
        } catch (VendorOrderLifecycleException $e) {
            $this->assertSame(VendorOrderLifecycleException::ILLEGAL_TRANSITION, $e->errorCode);
        }

        try {
            $this->lifecycle->deliver($owner, $order);
            $this->fail('Expected illegal skip pending→delivered');
        } catch (VendorOrderLifecycleException $e) {
            $this->assertSame(VendorOrderLifecycleException::ILLEGAL_TRANSITION, $e->errorCode);
        }

        $this->assertSame(VendorOrderStatus::Pending, $order->fresh()->status);

        $confirmed = $this->lifecycle->confirm($owner, $order);

        try {
            $this->lifecycle->confirm($owner, $confirmed);
            $this->fail('Expected illegal confirmed→confirmed');
        } catch (VendorOrderLifecycleException $e) {
            $this->assertSame(VendorOrderLifecycleException::ILLEGAL_TRANSITION, $e->errorCode);
        }

        $shipped = $this->lifecycle->ship($owner, $confirmed);
        $delivered = $this->lifecycle->deliver($owner, $shipped);

        try {
            $this->lifecycle->ship($owner, $delivered);
            $this->fail('Expected illegal regression delivered→shipped');
        } catch (VendorOrderLifecycleException $e) {
            $this->assertSame(VendorOrderLifecycleException::ILLEGAL_TRANSITION, $e->errorCode);
        }

        $this->assertSame(VendorOrderStatus::Delivered, $delivered->fresh()->status);
    }

    public function test_processing_and_cancelled_have_no_forward_path_in_vol(): void
    {
        [$owner, , $order] = $this->pendingOrderOwnedByVendor();

        $order->forceFill(['status' => VendorOrderStatus::Processing])->save();

        try {
            $this->lifecycle->ship($owner, $order);
            $this->fail('Expected illegal transition from processing');
        } catch (VendorOrderLifecycleException $e) {
            $this->assertSame(VendorOrderLifecycleException::ILLEGAL_TRANSITION, $e->errorCode);
        }

        $order->forceFill(['status' => VendorOrderStatus::Cancelled])->save();

        try {
            $this->lifecycle->confirm($owner, $order);
            $this->fail('Expected illegal transition from cancelled');
        } catch (VendorOrderLifecycleException $e) {
            $this->assertSame(VendorOrderLifecycleException::ILLEGAL_TRANSITION, $e->errorCode);
        }
    }

    public function test_non_owner_vendor_and_customer_cannot_advance(): void
    {
        Notification::fake();

        [$owner, $customer, $order] = $this->pendingOrderOwnedByVendor();
        $stranger = $this->createVendorUser();

        try {
            $this->lifecycle->confirm($stranger->fresh(['vendor', 'roles']), $order);
            $this->fail('Expected unauthorized for other vendor');
        } catch (VendorOrderLifecycleException $e) {
            $this->assertSame(VendorOrderLifecycleException::UNAUTHORIZED, $e->errorCode);
        }

        try {
            $this->lifecycle->confirm($customer, $order);
            $this->fail('Expected unauthorized for customer');
        } catch (VendorOrderLifecycleException $e) {
            $this->assertSame(VendorOrderLifecycleException::UNAUTHORIZED, $e->errorCode);
        }

        $this->assertSame(VendorOrderStatus::Pending, $order->fresh()->status);
        Notification::assertNothingSent();

        $this->assertTrue($owner->can('advance', $order));
        $this->assertFalse($stranger->fresh(['vendor', 'roles'])->can('advance', $order));
        $this->assertFalse($customer->can('advance', $order));
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
