<?php

namespace Tests\Feature;

use App\Enums\ParentOrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\VendorOrderStatus;
use App\Exceptions\OrderCancellationException;
use App\Models\OrderItem;
use App\Models\ParentOrder;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VendorOrder;
use App\Notifications\OrderCancelledCustomerNotification;
use App\Notifications\OrderCancelledVendorNotification;
use App\Services\OrderCancellationService;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\SyriaGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderCancellationCanATest extends TestCase
{
    use RefreshDatabase;

    private OrderCancellationService $cancellations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            SyriaGeoSeeder::class,
        ]);

        $this->cancellations = app(OrderCancellationService::class);
    }

    public function test_customer_cancels_multi_vendor_parent_restores_stock_cancels_payments_and_notifies(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $vendorA = $this->createVendorUser();
        $vendorB = $this->createVendorUser();

        [$variantA, $stockA] = $this->variantForStore($vendorA->vendor->store, initialQty: 7);
        [$variantB, $stockB] = $this->variantForStore($vendorB->vendor->store, initialQty: 4);

        $parent = ParentOrder::factory()->for($customer)->create([
            'status' => ParentOrderStatus::Placed,
        ]);

        $orderA = $this->pendingVendorOrderWithItem($parent, $vendorA, $variantA, qty: 2, stockAfterPlace: 5);
        $orderB = $this->pendingVendorOrderWithItem($parent, $vendorB, $variantB, qty: 1, stockAfterPlace: 3);

        $this->assertSame(5, $variantA->fresh()->quantity);
        $this->assertSame(3, $variantB->fresh()->quantity);

        $cancelled = $this->cancellations->cancelParentByCustomer($customer, $parent);

        $this->assertSame(ParentOrderStatus::Cancelled, $cancelled->status);
        $this->assertSame(VendorOrderStatus::Cancelled, $orderA->fresh()->status);
        $this->assertSame(VendorOrderStatus::Cancelled, $orderB->fresh()->status);
        $this->assertSame(PaymentStatus::Cancelled, $orderA->fresh()->payment?->status);
        $this->assertSame(PaymentStatus::Cancelled, $orderB->fresh()->payment?->status);
        $this->assertSame($stockA, $variantA->fresh()->quantity);
        $this->assertSame($stockB, $variantB->fresh()->quantity);

        Notification::assertSentTo($customer, OrderCancelledCustomerNotification::class, function ($notification) use ($parent) {
            return $notification->parentOrder->is($parent) && $notification->vendorOrder === null;
        });
        Notification::assertSentTo($vendorA, OrderCancelledVendorNotification::class, function ($notification) use ($orderA) {
            return $notification->vendorOrder->is($orderA);
        });
        Notification::assertSentTo($vendorB, OrderCancelledVendorNotification::class, function ($notification) use ($orderB) {
            return $notification->vendorOrder->is($orderB);
        });

        $this->assertTrue($customer->can('cancel', $parent));
    }

    public function test_vendor_cancels_own_vo_parent_stays_until_last_vo_then_cancels(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $vendorA = $this->createVendorUser();
        $vendorB = $this->createVendorUser();

        [$variantA, $stockA] = $this->variantForStore($vendorA->vendor->store, initialQty: 10);
        [$variantB, $stockB] = $this->variantForStore($vendorB->vendor->store, initialQty: 8);

        $parent = ParentOrder::factory()->for($customer)->create([
            'status' => ParentOrderStatus::Placed,
        ]);

        $orderA = $this->pendingVendorOrderWithItem($parent, $vendorA, $variantA, qty: 3, stockAfterPlace: 7);
        $orderB = $this->pendingVendorOrderWithItem($parent, $vendorB, $variantB, qty: 2, stockAfterPlace: 6);

        $orderA->forceFill(['status' => VendorOrderStatus::Confirmed])->save();

        $cancelledA = $this->cancellations->cancelVendorOrderByVendor($vendorA, $orderA->fresh());

        $this->assertSame(VendorOrderStatus::Cancelled, $cancelledA->status);
        $this->assertSame(PaymentStatus::Cancelled, $cancelledA->payment?->status);
        $this->assertSame($stockA, $variantA->fresh()->quantity);
        $this->assertSame(ParentOrderStatus::Placed, $parent->fresh()->status);
        $this->assertSame(VendorOrderStatus::Pending, $orderB->fresh()->status);

        Notification::assertSentTo($customer, OrderCancelledCustomerNotification::class, function ($notification) use ($orderA) {
            return $notification->vendorOrder?->is($orderA) ?? false;
        });
        Notification::assertSentTo($vendorA, OrderCancelledVendorNotification::class);

        $this->cancellations->cancelVendorOrderByVendor($vendorB, $orderB->fresh());

        $this->assertSame(VendorOrderStatus::Cancelled, $orderB->fresh()->status);
        $this->assertSame($stockB, $variantB->fresh()->quantity);
        $this->assertSame(ParentOrderStatus::Cancelled, $parent->fresh()->status);
    }

    public function test_illegal_windows_are_rejected(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $vendor = $this->createVendorUser();
        [$variant] = $this->variantForStore($vendor->vendor->store, initialQty: 5);

        $parent = ParentOrder::factory()->for($customer)->create([
            'status' => ParentOrderStatus::Placed,
        ]);
        $order = $this->pendingVendorOrderWithItem($parent, $vendor, $variant, qty: 1, stockAfterPlace: 4);

        $order->forceFill(['status' => VendorOrderStatus::Shipped])->save();

        try {
            $this->cancellations->cancelVendorOrderByVendor($vendor, $order->fresh());
            $this->fail('Expected illegal cancel after shipped');
        } catch (OrderCancellationException $e) {
            $this->assertSame(OrderCancellationException::ILLEGAL_STATE, $e->errorCode);
        }

        $this->assertSame(VendorOrderStatus::Shipped, $order->fresh()->status);
        $this->assertSame(4, $variant->fresh()->quantity);

        $order->forceFill(['status' => VendorOrderStatus::Pending])->save();
        $siblingVendor = $this->createVendorUser();
        [$siblingVariant] = $this->variantForStore($siblingVendor->vendor->store, initialQty: 3);
        $sibling = $this->pendingVendorOrderWithItem($parent, $siblingVendor, $siblingVariant, qty: 1, stockAfterPlace: 2);
        $sibling->forceFill(['status' => VendorOrderStatus::Confirmed])->save();

        try {
            $this->cancellations->cancelParentByCustomer($customer, $parent->fresh());
            $this->fail('Expected illegal Parent cancel when any VO is not pending');
        } catch (OrderCancellationException $e) {
            $this->assertSame(OrderCancellationException::ILLEGAL_STATE, $e->errorCode);
        }

        $this->assertSame(ParentOrderStatus::Placed, $parent->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_strangers_cannot_cancel(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $owner = $this->createVendorUser();
        $stranger = $this->createVendorUser();
        $otherCustomer = User::factory()->create();

        [$variant] = $this->variantForStore($owner->vendor->store, initialQty: 6);

        $parent = ParentOrder::factory()->for($customer)->create([
            'status' => ParentOrderStatus::Placed,
        ]);
        $order = $this->pendingVendorOrderWithItem($parent, $owner, $variant, qty: 2, stockAfterPlace: 4);

        try {
            $this->cancellations->cancelVendorOrderByVendor($stranger, $order);
            $this->fail('Expected unauthorized for stranger vendor');
        } catch (OrderCancellationException $e) {
            $this->assertSame(OrderCancellationException::UNAUTHORIZED, $e->errorCode);
        }

        try {
            $this->cancellations->cancelVendorOrderByVendor($customer, $order);
            $this->fail('Expected unauthorized for customer on vendor cancel');
        } catch (OrderCancellationException $e) {
            $this->assertSame(OrderCancellationException::UNAUTHORIZED, $e->errorCode);
        }

        try {
            $this->cancellations->cancelParentByCustomer($otherCustomer, $parent);
            $this->fail('Expected unauthorized for other customer');
        } catch (OrderCancellationException $e) {
            $this->assertSame(OrderCancellationException::UNAUTHORIZED, $e->errorCode);
        }

        $this->assertSame(VendorOrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(ParentOrderStatus::Placed, $parent->fresh()->status);
        $this->assertSame(4, $variant->fresh()->quantity);
        Notification::assertNothingSent();

        $this->assertFalse($stranger->can('cancel', $order));
        $this->assertFalse($otherCustomer->can('cancel', $parent));
        $this->assertTrue($owner->can('cancel', $order));
        $this->assertTrue($customer->can('cancel', $parent));
    }

    public function test_collected_payment_blocks_cancel(): void
    {
        $customer = User::factory()->create();
        $vendor = $this->createVendorUser();
        [$variant] = $this->variantForStore($vendor->vendor->store, initialQty: 5);

        $parent = ParentOrder::factory()->for($customer)->create([
            'status' => ParentOrderStatus::Placed,
        ]);
        $order = $this->pendingVendorOrderWithItem($parent, $vendor, $variant, qty: 1, stockAfterPlace: 4);
        $order->payment?->forceFill(['status' => PaymentStatus::Collected])->save();

        try {
            $this->cancellations->cancelVendorOrderByVendor($vendor, $order->fresh());
            $this->fail('Expected illegal cancel when payment is collected');
        } catch (OrderCancellationException $e) {
            $this->assertSame(OrderCancellationException::ILLEGAL_STATE, $e->errorCode);
        }

        $this->assertSame(VendorOrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Collected, $order->fresh()->payment?->status);
        $this->assertSame(4, $variant->fresh()->quantity);
    }

    /**
     * @return array{0: ProductVariant, 1: int}
     */
    private function variantForStore($store, int $initialQty): array
    {
        $product = Product::factory()->create([
            'store_id' => $store->id,
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity' => $initialQty,
        ]);

        return [$variant, $initialQty];
    }

    private function pendingVendorOrderWithItem(
        ParentOrder $parent,
        User $vendorUser,
        ProductVariant $variant,
        int $qty,
        int $stockAfterPlace,
    ): VendorOrder {
        $store = $vendorUser->vendor->store;

        $order = VendorOrder::factory()
            ->forStore($store)
            ->for($parent)
            ->create([
                'status' => VendorOrderStatus::Pending,
                'commission_recognized_at' => null,
            ]);

        OrderItem::factory()->for($order)->create([
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
            'store_id' => $store->id,
            'vendor_id' => $store->vendor_id,
            'quantity' => $qty,
            'unit_price_amount_minor' => 1000,
            'line_total_amount_minor' => 1000 * $qty,
            'currency_code' => $order->currency_code,
        ]);

        Payment::factory()->for($order)->create([
            'status' => PaymentStatus::Pending,
            'amount_minor' => $order->grand_total_amount_minor,
            'currency_code' => $order->currency_code,
        ]);

        $variant->forceFill(['quantity' => $stockAfterPlace])->save();

        return $order->fresh(['payment', 'items', 'parentOrder', 'vendor.user']);
    }
}
