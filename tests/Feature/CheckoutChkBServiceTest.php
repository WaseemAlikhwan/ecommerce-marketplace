<?php

namespace Tests\Feature;

use App\Enums\ParentOrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\VendorOrderStatus;
use App\Exceptions\CheckoutException;
use App\Models\Brand;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\OrderItem;
use App\Models\ParentOrder;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VendorOrder;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\ProductImageService;
use App\Services\ProductPublicationService;
use App\Services\ProductService;
use Database\Seeders\CommissionSettingSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\SyriaGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckoutChkBServiceTest extends TestCase
{
    use RefreshDatabase;

    private CheckoutService $checkout;

    private CartService $carts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            SyriaGeoSeeder::class,
            CommissionSettingSeeder::class,
        ]);

        $this->checkout = app(CheckoutService::class);
        $this->carts = app(CartService::class);
    }

    public function test_happy_path_creates_parent_vendor_items_decrements_stock_and_clears_cart(): void
    {
        $customer = User::factory()->create();
        $address = $this->addressFor($customer);

        $syp = $this->publishPurchasableVariant(quantity: 5, currency: 'SYP', price: '100', skuSuffix: 'A');
        $usd = $this->publishPurchasableVariant(quantity: 5, currency: 'USD', price: '10.00', skuSuffix: 'B');

        $this->carts->add($customer, $syp->id, 2);
        $this->carts->add($customer, $usd->id, 1);

        $result = $this->checkout->placeOrder($customer, $address);

        $parent = $result->parentOrder;
        $this->assertSame(ParentOrderStatus::Placed, $parent->status);
        $this->assertStringStartsWith('PO-', $parent->public_code);
        $this->assertSame($customer->id, $parent->user_id);
        $this->assertSame($address->recipient_name, $parent->shipping_recipient_name);
        $this->assertCount(2, $parent->vendorOrders);

        foreach ($parent->vendorOrders as $vendorOrder) {
            $this->assertStringStartsWith('VO-', $vendorOrder->public_code);
            $this->assertSame(VendorOrderStatus::Pending, $vendorOrder->status);
            $this->assertNull($vendorOrder->commission_recognized_at);
            $this->assertSame(0, $vendorOrder->shipping_amount_minor);
            $this->assertSame(
                $vendorOrder->items_subtotal_amount_minor,
                $vendorOrder->commission_base_amount_minor,
            );
            $this->assertSame(
                intdiv($vendorOrder->items_subtotal_amount_minor * $vendorOrder->commission_rate_bps, 10_000),
                $vendorOrder->commission_amount_minor,
            );

            $payment = $vendorOrder->payment;
            $this->assertNotNull($payment);
            $this->assertSame(PaymentMethod::Cod, $payment->method);
            $this->assertSame(PaymentStatus::Pending, $payment->status);
            $this->assertSame($vendorOrder->grand_total_amount_minor, $payment->amount_minor);
        }

        $this->assertSame(3, $syp->fresh()->quantity);
        $this->assertSame(4, $usd->fresh()->quantity);
        $this->assertSame(0, CartItem::query()->count());
        $this->assertSame([], $this->carts->lines($customer)->all());
    }

    public function test_empty_cart_fails_without_creating_orders(): void
    {
        $customer = User::factory()->create();
        $address = $this->addressFor($customer);

        try {
            $this->checkout->placeOrder($customer, $address);
            $this->fail('Expected CheckoutException');
        } catch (CheckoutException $e) {
            $this->assertSame(CheckoutException::EMPTY_CART, $e->errorCode);
        }

        $this->assertSame(0, ParentOrder::query()->count());
        $this->assertSame(0, VendorOrder::query()->count());
        $this->assertSame(0, OrderItem::query()->count());
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_unavailable_variant_rolls_back_completely(): void
    {
        $customer = User::factory()->create();
        $address = $this->addressFor($customer);

        $live = $this->publishPurchasableVariant(quantity: 3, skuSuffix: 'LIVE');
        $doomed = $this->publishPurchasableVariant(quantity: 3, skuSuffix: 'DOOM');

        $this->carts->add($customer, $live->id, 1);
        $this->carts->add($customer, $doomed->id, 1);

        $doomed->product->forceFill(['status' => 'draft'])->save();

        try {
            $this->checkout->placeOrder($customer, $address);
            $this->fail('Expected CheckoutException');
        } catch (CheckoutException $e) {
            $this->assertSame(CheckoutException::UNAVAILABLE_VARIANT, $e->errorCode);
        }

        $this->assertSame(0, ParentOrder::query()->count());
        $this->assertSame(0, VendorOrder::query()->count());
        $this->assertSame(0, OrderItem::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(3, $live->fresh()->quantity);
        $this->assertSame(3, $doomed->fresh()->quantity);
        $this->assertCount(2, $this->carts->lines($customer));
    }

    public function test_concurrent_stock_safety_second_checkout_fails(): void
    {
        $variant = $this->publishPurchasableVariant(quantity: 1, skuSuffix: 'RACE');

        $buyerA = User::factory()->create();
        $buyerB = User::factory()->create();
        $addressA = $this->addressFor($buyerA);
        $addressB = $this->addressFor($buyerB);

        $this->carts->add($buyerA, $variant->id, 1);
        $this->carts->add($buyerB, $variant->id, 1);

        $first = $this->checkout->placeOrder($buyerA, $addressA);
        $this->assertInstanceOf(ParentOrder::class, $first->parentOrder);

        try {
            $this->checkout->placeOrder($buyerB, $addressB);
            $this->fail('Expected CheckoutException for oversell');
        } catch (CheckoutException $e) {
            $this->assertContains($e->errorCode, [
                CheckoutException::UNAVAILABLE_VARIANT,
                CheckoutException::INSUFFICIENT_STOCK,
            ]);
        }

        $this->assertSame(0, $variant->fresh()->quantity);
        $this->assertSame(1, ParentOrder::query()->count());
        $this->assertSame(1, VendorOrder::query()->count());
        $this->assertCount(0, $this->carts->lines($buyerA));
        $this->assertCount(1, $this->carts->lines($buyerB));
    }

    public function test_vendor_orders_are_isolated_per_vendor(): void
    {
        $customer = User::factory()->create();
        $address = $this->addressFor($customer);

        $variantA = $this->publishPurchasableVariant(quantity: 4, skuSuffix: 'VA');
        $variantB = $this->publishPurchasableVariant(quantity: 4, skuSuffix: 'VB');
        $this->assertNotSame($variantA->store_id, $variantB->store_id);

        $this->carts->add($customer, $variantA->id, 1);
        $this->carts->add($customer, $variantB->id, 2);

        $result = $this->checkout->placeOrder($customer, $address);
        $vendorOrders = $result->parentOrder->vendorOrders;
        $this->assertCount(2, $vendorOrders);

        $orderA = $vendorOrders->firstWhere('vendor_id', $variantA->product->store->vendor_id);
        $orderB = $vendorOrders->firstWhere('vendor_id', $variantB->product->store->vendor_id);
        $this->assertNotNull($orderA);
        $this->assertNotNull($orderB);
        $this->assertCount(1, $orderA->items);
        $this->assertCount(1, $orderB->items);
        $this->assertSame($variantA->id, $orderA->items->first()->variant_id);
        $this->assertSame($variantB->id, $orderB->items->first()->variant_id);

        $vendorUserA = $variantA->product->store->vendor->user;
        $vendorUserB = $variantB->product->store->vendor->user;
        $vendorUserA->assignRole('vendor');
        $vendorUserB->assignRole('vendor');

        $this->assertTrue(Gate::forUser($vendorUserA->fresh(['vendor', 'roles']))->allows('view', $orderA));
        $this->assertFalse(Gate::forUser($vendorUserA->fresh(['vendor', 'roles']))->allows('view', $orderB));
        $this->assertTrue(Gate::forUser($vendorUserB->fresh(['vendor', 'roles']))->allows('view', $orderB));
        $this->assertFalse(Gate::forUser($vendorUserB->fresh(['vendor', 'roles']))->allows('view', $orderA));
    }

    public function test_mixed_syp_and_usd_dues_without_fx(): void
    {
        $customer = User::factory()->create();
        $address = $this->addressFor($customer);

        $syp = $this->publishPurchasableVariant(quantity: 5, currency: 'SYP', price: '250', skuSuffix: 'SY');
        $usd = $this->publishPurchasableVariant(quantity: 5, currency: 'USD', price: '12.50', skuSuffix: 'US');

        $this->carts->add($customer, $syp->id, 2); // 500 SYP
        $this->carts->add($customer, $usd->id, 1); // 1250 USD minor

        $result = $this->checkout->placeOrder($customer, $address);

        $this->assertSame([
            'SYP' => 500,
            'USD' => 1250,
        ], $result->codDuesMinorByCurrency);

        $sypOrder = $result->parentOrder->vendorOrders->firstWhere('currency_code', 'SYP');
        $usdOrder = $result->parentOrder->vendorOrders->firstWhere('currency_code', 'USD');
        $this->assertNotNull($sypOrder);
        $this->assertNotNull($usdOrder);
        $this->assertSame(500, $sypOrder->grand_total_amount_minor);
        $this->assertSame(1250, $usdOrder->grand_total_amount_minor);
        $this->assertSame('SYP', $sypOrder->payment->currency_code);
        $this->assertSame('USD', $usdOrder->payment->currency_code);
    }

    private function addressFor(User $customer): CustomerAddress
    {
        return CustomerAddress::factory()->for($customer)->default()->create();
    }

    private function publishPurchasableVariant(
        int $quantity,
        string $currency = 'SYP',
        string $price = '100',
        string $skuSuffix = '1',
    ): ProductVariant {
        Storage::fake('public');

        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);

        $category = Category::factory()->create(['is_active' => true]);
        $brand = Brand::factory()->create(['is_active' => true]);

        $product = app(ProductService::class)->createSimpleDraft($vendor->vendor->store, [
            'type' => 'simple',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'currency_code' => $currency,
            'sku' => 'CHK-'.$skuSuffix.'-'.uniqid(),
            'price' => $price,
            'quantity' => $quantity,
            'translations' => [
                'ar' => ['name' => 'منتج طلب '.$skuSuffix],
                'en' => ['name' => 'Checkout Product '.$skuSuffix],
            ],
        ]);

        app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());
        app(ProductPublicationService::class)->publish($product->fresh());

        $product = $product->fresh(['defaultVariant']);
        $this->assertTrue(Product::query()->storefrontVisible()->whereKey($product->id)->exists());

        $variant = $product->defaultVariant;
        $this->assertNotNull($variant);
        $variant->forceFill(['quantity' => $quantity])->save();

        return $variant->fresh(['product.store.vendor.user']);
    }
}
