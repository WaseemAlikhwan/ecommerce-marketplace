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

class OrderCancellationCanCTest extends TestCase
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

    public function test_owner_can_cancel_parent_from_show_with_all_vos_stock_and_payments(): void
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

        $this->actingAs($customer)
            ->get(route('account.orders.show', $parent))
            ->assertOk()
            ->assertSee('data-cancel-action', false)
            ->assertSee(__('Cancel order'), false)
            ->assertDontSee('data-advance-action')
            ->assertDontSee(__('Mark shipped'), false)
            ->assertDontSee(route('vendor.orders.advance', $orderA), false);

        $this->actingAs($customer)
            ->from(route('account.orders.show', $parent))
            ->post(route('account.orders.cancel', $parent))
            ->assertRedirect(route('account.orders.show', $parent))
            ->assertSessionHas('status', __('Order cancelled.'));

        $this->assertSame(ParentOrderStatus::Cancelled, $parent->fresh()->status);
        $this->assertSame(VendorOrderStatus::Cancelled, $orderA->fresh()->status);
        $this->assertSame(VendorOrderStatus::Cancelled, $orderB->fresh()->status);
        $this->assertSame(PaymentStatus::Cancelled, $orderA->fresh()->payment?->status);
        $this->assertSame(PaymentStatus::Cancelled, $orderB->fresh()->payment?->status);
        $this->assertSame($stockA, $variantA->fresh()->quantity);
        $this->assertSame($stockB, $variantB->fresh()->quantity);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $parent->fresh()))
            ->assertOk()
            ->assertDontSee('data-cancel-action')
            ->assertSee(__('Cancelled'), false)
            ->assertDontSee('data-advance-action');
    }

    public function test_stranger_cancel_is_not_found(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $stranger = User::factory()->create();
        $vendor = $this->createVendorUser();
        [$variant, $stockInitial] = $this->variantForStore($vendor->vendor->store, initialQty: 5);

        $parent = ParentOrder::factory()->for($customer)->create([
            'status' => ParentOrderStatus::Placed,
        ]);
        $order = $this->pendingVendorOrderWithItem($parent, $vendor, $variant, qty: 1, stockAfterPlace: 4);

        $this->actingAs($stranger)
            ->post(route('account.orders.cancel', $parent))
            ->assertNotFound();

        $this->assertSame(ParentOrderStatus::Placed, $parent->fresh()->status);
        $this->assertSame(VendorOrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment?->status);
        $this->assertSame($stockInitial - 1, $variant->fresh()->quantity);
    }

    public function test_ineligible_parent_with_confirmed_vo_is_rejected(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $vendorA = $this->createVendorUser();
        $vendorB = $this->createVendorUser();

        [$variantA] = $this->variantForStore($vendorA->vendor->store, initialQty: 6);
        [$variantB] = $this->variantForStore($vendorB->vendor->store, initialQty: 5);

        $parent = ParentOrder::factory()->for($customer)->create([
            'status' => ParentOrderStatus::Placed,
        ]);

        $orderA = $this->pendingVendorOrderWithItem($parent, $vendorA, $variantA, qty: 1, stockAfterPlace: 5);
        $orderB = $this->pendingVendorOrderWithItem($parent, $vendorB, $variantB, qty: 1, stockAfterPlace: 4);
        $orderA->forceFill(['status' => VendorOrderStatus::Confirmed])->save();

        $this->actingAs($customer)
            ->get(route('account.orders.show', $parent->fresh(['vendorOrders'])))
            ->assertOk()
            ->assertDontSee('data-cancel-action')
            ->assertDontSee('data-advance-action');

        $this->actingAs($customer)
            ->from(route('account.orders.show', $parent))
            ->post(route('account.orders.cancel', $parent))
            ->assertRedirect(route('account.orders.show', $parent))
            ->assertSessionHasErrors('cancel');

        $this->assertSame(ParentOrderStatus::Placed, $parent->fresh()->status);
        $this->assertSame(VendorOrderStatus::Confirmed, $orderA->fresh()->status);
        $this->assertSame(VendorOrderStatus::Pending, $orderB->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $orderA->fresh()->payment?->status);
        $this->assertSame(5, $variantA->fresh()->quantity);
        $this->assertSame(4, $variantB->fresh()->quantity);
    }

    public function test_shipped_vo_makes_parent_ineligible_and_cancel_rejected(): void
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

        $this->actingAs($customer)
            ->get(route('account.orders.show', $parent->fresh(['vendorOrders'])))
            ->assertOk()
            ->assertDontSee('data-cancel-action');

        $this->actingAs($customer)
            ->from(route('account.orders.show', $parent))
            ->post(route('account.orders.cancel', $parent))
            ->assertRedirect(route('account.orders.show', $parent))
            ->assertSessionHasErrors('cancel');

        $this->assertSame(ParentOrderStatus::Placed, $parent->fresh()->status);
        $this->assertSame(VendorOrderStatus::Shipped, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment?->status);
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
