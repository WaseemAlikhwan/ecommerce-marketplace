<?php

namespace Tests\Feature;

use App\Enums\ParentOrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\VendorOrderStatus;
use App\Models\OrderItem;
use App\Models\ParentOrder;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VendorOrder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\SyriaGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderCancellationCanBTest extends TestCase
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

    public function test_owner_can_cancel_pending_order_from_show_with_stock_and_payment_side_effects(): void
    {
        Notification::fake();

        [$owner, , $order, $variant, $stockInitial] = $this->pendingOrderWithStockOwnedByVendor();

        $this->actingAs($owner)
            ->get(route('vendor.orders.show', $order))
            ->assertOk()
            ->assertSee('data-cancel-action', false)
            ->assertSee(__('Cancel order'), false);

        $this->actingAs($owner)
            ->from(route('vendor.orders.show', $order))
            ->post(route('vendor.orders.cancel', $order))
            ->assertRedirect(route('vendor.orders.show', $order))
            ->assertSessionHas('status', __('Order cancelled.'));

        $cancelled = $order->fresh(['payment']);
        $this->assertSame(VendorOrderStatus::Cancelled, $cancelled->status);
        $this->assertSame(PaymentStatus::Cancelled, $cancelled->payment?->status);
        $this->assertSame($stockInitial, $variant->fresh()->quantity);
        $this->assertSame(ParentOrderStatus::Cancelled, $cancelled->parentOrder->fresh()->status);

        $this->actingAs($owner)
            ->get(route('vendor.orders.show', $cancelled))
            ->assertOk()
            ->assertDontSee('data-cancel-action')
            ->assertSee(__('Cancelled'), false);
    }

    public function test_owner_can_cancel_confirmed_order_from_show(): void
    {
        Notification::fake();

        [$owner, , $order, $variant, $stockInitial] = $this->pendingOrderWithStockOwnedByVendor();
        $order->forceFill(['status' => VendorOrderStatus::Confirmed])->save();

        $this->actingAs($owner)
            ->get(route('vendor.orders.show', $order->fresh()))
            ->assertOk()
            ->assertSee('data-cancel-action', false);

        $this->actingAs($owner)
            ->post(route('vendor.orders.cancel', $order))
            ->assertRedirect(route('vendor.orders.show', $order))
            ->assertSessionHas('status', __('Order cancelled.'));

        $this->assertSame(VendorOrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Cancelled, $order->fresh()->payment?->status);
        $this->assertSame($stockInitial, $variant->fresh()->quantity);
    }

    public function test_non_owner_cancel_is_not_found(): void
    {
        Notification::fake();

        [, , $order, $variant] = $this->pendingOrderWithStockOwnedByVendor();
        $stockBefore = $variant->fresh()->quantity;
        $stranger = $this->createVendorUser();

        $this->actingAs($stranger)
            ->get(route('vendor.orders.show', $order))
            ->assertNotFound();

        $this->actingAs($stranger)
            ->post(route('vendor.orders.cancel', $order))
            ->assertNotFound();

        $this->assertSame(VendorOrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment?->status);
        $this->assertSame($stockBefore, $variant->fresh()->quantity);
    }

    public function test_shipped_and_delivered_cancel_are_rejected(): void
    {
        Notification::fake();

        [$owner, , $order, $variant] = $this->pendingOrderWithStockOwnedByVendor();
        $stockBefore = $variant->fresh()->quantity;

        foreach ([VendorOrderStatus::Shipped, VendorOrderStatus::Delivered] as $status) {
            $order->forceFill(['status' => $status])->save();

            $this->actingAs($owner)
                ->get(route('vendor.orders.show', $order->fresh()))
                ->assertOk()
                ->assertDontSee('data-cancel-action');

            $this->actingAs($owner)
                ->from(route('vendor.orders.show', $order))
                ->post(route('vendor.orders.cancel', $order))
                ->assertRedirect(route('vendor.orders.show', $order))
                ->assertSessionHasErrors('cancel');

            $this->assertSame($status, $order->fresh()->status);
            $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment?->status);
            $this->assertSame($stockBefore, $variant->fresh()->quantity);
        }
    }

    public function test_collected_payment_cancel_is_rejected_without_mutation(): void
    {
        Notification::fake();

        [$owner, , $order, $variant] = $this->pendingOrderWithStockOwnedByVendor();
        $order->payment?->forceFill(['status' => PaymentStatus::Collected])->save();
        $stockBefore = $variant->fresh()->quantity;

        $this->actingAs($owner)
            ->from(route('vendor.orders.show', $order))
            ->post(route('vendor.orders.cancel', $order))
            ->assertRedirect(route('vendor.orders.show', $order))
            ->assertSessionHasErrors('cancel');

        $this->assertSame(VendorOrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Collected, $order->fresh()->payment?->status);
        $this->assertSame($stockBefore, $variant->fresh()->quantity);
    }

    /**
     * @return array{0: User, 1: User, 2: VendorOrder, 3: ProductVariant, 4: int}
     */
    private function pendingOrderWithStockOwnedByVendor(): array
    {
        $owner = $this->createVendorUser();
        $customer = User::factory()->create();
        $store = $owner->vendor->store;

        $product = Product::factory()->create([
            'store_id' => $store->id,
        ]);

        $initialQty = 7;
        $qtyOrdered = 2;
        $stockAfterPlace = 5;

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity' => $stockAfterPlace,
        ]);

        $parent = ParentOrder::factory()->for($customer)->create([
            'status' => ParentOrderStatus::Placed,
        ]);

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
            'quantity' => $qtyOrdered,
            'unit_price_amount_minor' => 1000,
            'line_total_amount_minor' => 1000 * $qtyOrdered,
            'currency_code' => $order->currency_code,
        ]);

        Payment::factory()->for($order)->create([
            'status' => PaymentStatus::Pending,
            'amount_minor' => $order->grand_total_amount_minor,
            'currency_code' => $order->currency_code,
        ]);

        return [
            $owner->fresh(['vendor', 'roles']),
            $customer->fresh(),
            $order->fresh(['payment', 'parentOrder', 'vendor.user', 'items']),
            $variant->fresh(),
            $initialQty,
        ];
    }
}
