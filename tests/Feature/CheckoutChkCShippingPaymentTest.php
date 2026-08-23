<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Contracts\ShippingCalculator;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Payments\CodPaymentGateway;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\ProductImageService;
use App\Services\ProductPublicationService;
use App\Services\ProductService;
use App\Shipping\FlatPerVendorShippingCalculator;
use Database\Seeders\CommissionSettingSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\SyriaGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckoutChkCShippingPaymentTest extends TestCase
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

        config([
            'shipping.flat_fee_defaults_minor' => [
                'SYP' => 5_000,
                'USD' => 300,
            ],
        ]);

        $this->checkout = app(CheckoutService::class);
        $this->carts = app(CartService::class);
    }

    public function test_container_binds_flat_shipping_calculator_and_cod_gateway_only(): void
    {
        $this->assertInstanceOf(FlatPerVendorShippingCalculator::class, app(ShippingCalculator::class));
        $this->assertInstanceOf(CodPaymentGateway::class, app(PaymentGateway::class));
        $this->assertSame([PaymentMethod::Cod], PaymentMethod::cases());
    }

    public function test_store_flat_shipping_override_beats_platform_default(): void
    {
        $customer = User::factory()->create();
        $address = $this->addressFor($customer);

        $variant = $this->publishPurchasableVariant(quantity: 5, currency: 'SYP', price: '100', skuSuffix: 'OV');
        $store = $variant->product->store;
        $store->forceFill(['flat_shipping_amount_minor' => 12_500])->save();

        $this->carts->add($customer, $variant->id, 2); // items 200

        $result = $this->checkout->placeOrder($customer, $address);
        $vendorOrder = $result->parentOrder->vendorOrders->first();

        $this->assertNotNull($vendorOrder);
        $this->assertSame(12_500, $vendorOrder->shipping_amount_minor);
        $this->assertSame(200, $vendorOrder->items_subtotal_amount_minor);
        $this->assertSame(12_700, $vendorOrder->grand_total_amount_minor);
        $this->assertSame(['SYP' => 12_700], $result->codDuesMinorByCurrency);

        $payment = $vendorOrder->payment;
        $this->assertNotNull($payment);
        $this->assertSame(PaymentMethod::Cod, $payment->method);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(12_700, $payment->amount_minor);
    }

    public function test_platform_default_applies_when_store_shipping_is_null(): void
    {
        $customer = User::factory()->create();
        $address = $this->addressFor($customer);

        $variant = $this->publishPurchasableVariant(quantity: 5, currency: 'USD', price: '10.00', skuSuffix: 'DF');
        $store = $variant->product->store;
        $store->forceFill(['flat_shipping_amount_minor' => null])->save();

        $this->carts->add($customer, $variant->id, 1); // items 1000 minor

        $result = $this->checkout->placeOrder($customer, $address);
        $vendorOrder = $result->parentOrder->vendorOrders->first();

        $this->assertNotNull($vendorOrder);
        $this->assertSame(300, $vendorOrder->shipping_amount_minor);
        $this->assertSame(1_000, $vendorOrder->items_subtotal_amount_minor);
        $this->assertSame(1_300, $vendorOrder->grand_total_amount_minor);
        $this->assertSame(1_300, $vendorOrder->payment->amount_minor);
        $this->assertSame(['USD' => 1_300], $result->codDuesMinorByCurrency);
    }

    public function test_multi_vendor_orders_get_per_vendor_shipping_and_pending_cod_rows(): void
    {
        $customer = User::factory()->create();
        $address = $this->addressFor($customer);

        $a = $this->publishPurchasableVariant(quantity: 5, currency: 'SYP', price: '100', skuSuffix: 'MA');
        $b = $this->publishPurchasableVariant(quantity: 5, currency: 'SYP', price: '50', skuSuffix: 'MB');

        $a->product->store->forceFill(['flat_shipping_amount_minor' => 2_000])->save();
        $b->product->store->forceFill(['flat_shipping_amount_minor' => 7_500])->save();

        $this->carts->add($customer, $a->id, 1); // 100 + 2000
        $this->carts->add($customer, $b->id, 2); // 100 + 7500

        $result = $this->checkout->placeOrder($customer, $address);
        $orders = $result->parentOrder->vendorOrders;
        $this->assertCount(2, $orders);

        $orderA = $orders->firstWhere('store_id', $a->product->store_id);
        $orderB = $orders->firstWhere('store_id', $b->product->store_id);
        $this->assertNotNull($orderA);
        $this->assertNotNull($orderB);

        $this->assertSame(2_000, $orderA->shipping_amount_minor);
        $this->assertSame(2_100, $orderA->grand_total_amount_minor);
        $this->assertSame(2_100, $orderA->payment->amount_minor);
        $this->assertSame(PaymentStatus::Pending, $orderA->payment->status);
        $this->assertSame(PaymentMethod::Cod, $orderA->payment->method);

        $this->assertSame(7_500, $orderB->shipping_amount_minor);
        $this->assertSame(7_600, $orderB->grand_total_amount_minor);
        $this->assertSame(7_600, $orderB->payment->amount_minor);

        $this->assertSame(2, Payment::query()->count());
        $this->assertSame(0, Payment::query()->where('method', '!=', PaymentMethod::Cod->value)->count());
        $this->assertSame(['SYP' => 9_700], $result->codDuesMinorByCurrency);
    }

    public function test_calculator_reads_fees_from_store_or_config_not_hard_coded_constants(): void
    {
        $calculator = app(ShippingCalculator::class);
        $variant = $this->publishPurchasableVariant(quantity: 1, currency: 'SYP', skuSuffix: 'CFG');
        $store = $variant->product->store;
        $vendor = $store->vendor;

        $store->forceFill(['flat_shipping_amount_minor' => null])->save();
        $this->assertSame(5_000, $calculator->feeForVendorOrder($vendor, $store->fresh(), 'SYP'));
        $this->assertSame(300, $calculator->feeForVendorOrder($vendor, $store->fresh(), 'USD'));

        config(['shipping.flat_fee_defaults_minor.SYP' => 9_999]);
        $this->assertSame(9_999, $calculator->feeForVendorOrder($vendor, $store->fresh(), 'SYP'));

        $store->forceFill(['flat_shipping_amount_minor' => 111])->save();
        $this->assertSame(111, $calculator->feeForVendorOrder($vendor, $store->fresh(), 'SYP'));
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
            'sku' => 'CHKC-'.$skuSuffix.'-'.uniqid(),
            'price' => $price,
            'quantity' => $quantity,
            'translations' => [
                'ar' => ['name' => 'منتج شحن '.$skuSuffix],
                'en' => ['name' => 'Shipping Product '.$skuSuffix],
            ],
        ]);

        app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());
        app(ProductPublicationService::class)->publish($product->fresh());

        $product = $product->fresh(['defaultVariant']);
        $this->assertTrue(Product::query()->storefrontVisible()->whereKey($product->id)->exists());

        $variant = $product->defaultVariant;
        $this->assertNotNull($variant);
        $variant->forceFill(['quantity' => $quantity])->save();

        return $variant->fresh(['product.store.vendor']);
    }
}
