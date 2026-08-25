<?php

namespace Tests\Feature;

use App\Coupons\CheckoutCouponSession;
use App\Enums\CouponRedemptionStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\CustomerAddress;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderCancellationService;
use App\Services\ProductImageService;
use App\Services\ProductPublicationService;
use App\Services\ProductService;
use App\Support\Locale;
use Database\Seeders\CommissionSettingSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\SyriaGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CouponsCpnB1Test extends TestCase
{
    use RefreshDatabase;

    private CartService $carts;

    private CheckoutService $checkout;

    private OrderCancellationService $cancellations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            SyriaGeoSeeder::class,
            CommissionSettingSeeder::class,
        ]);

        $this->carts = app(CartService::class);
        $this->checkout = app(CheckoutService::class);
        $this->cancellations = app(OrderCancellationService::class);
    }

    public function test_guest_coupon_mutations_redirect_to_login(): void
    {
        $this->post(route('checkout.coupon.apply'), ['code' => 'SAVE10'])
            ->assertRedirect(route('login'));

        $this->delete(route('checkout.coupon.remove'))
            ->assertRedirect(route('login'));
    }

    public function test_customer_can_apply_and_remove_coupon_on_checkout(): void
    {
        $customer = User::factory()->create(['preferred_locale' => 'en']);
        $variant = $this->publishPurchasableVariant(quantity: 5, price: '1000', skuSuffix: 'B1A');
        $this->carts->add($customer, $variant->id, 1);
        $this->addressFor($customer);

        Coupon::factory()->platform()->percent(10)->create([
            'code' => 'SAVE10',
            'currency_code' => 'SYP',
        ]);

        $this->actingAs($customer)
            ->withCookie(Locale::COOKIE, 'en')
            ->post(route('checkout.coupon.apply'), ['code' => 'save10'])
            ->assertRedirect(route('checkout.create'))
            ->assertSessionHas('status');

        $this->assertSame('SAVE10', CheckoutCouponSession::get());

        $this->actingAs($customer)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('checkout.create'))
            ->assertOk()
            ->assertSee('SAVE10', false)
            ->assertSee('Discount', false)
            ->assertDontSee($variant->sku, false);

        $this->actingAs($customer)
            ->delete(route('checkout.coupon.remove'))
            ->assertRedirect(route('checkout.create'));

        $this->assertNull(CheckoutCouponSession::get());
    }

    public function test_second_distinct_coupon_is_rejected_until_first_removed(): void
    {
        $customer = User::factory()->create();
        $variant = $this->publishPurchasableVariant(quantity: 5, price: '1000', skuSuffix: 'B1B');
        $this->carts->add($customer, $variant->id, 1);
        $this->addressFor($customer);

        Coupon::factory()->platform()->percent(10)->create([
            'code' => 'SAVE10',
            'currency_code' => 'SYP',
        ]);
        Coupon::factory()->platform()->percent(5)->create([
            'code' => 'SAVE5',
            'currency_code' => 'SYP',
        ]);

        $this->actingAs($customer)
            ->post(route('checkout.coupon.apply'), ['code' => 'SAVE10'])
            ->assertSessionHasNoErrors();

        $this->actingAs($customer)
            ->from(route('checkout.create'))
            ->post(route('checkout.coupon.apply'), ['code' => 'SAVE5'])
            ->assertRedirect(route('checkout.create'))
            ->assertSessionHasErrors('coupon');

        $this->assertSame('SAVE10', CheckoutCouponSession::get());
    }

    public function test_place_order_snapshots_redeems_keeps_commission_pre_coupon(): void
    {
        $customer = User::factory()->create();
        $address = $this->addressFor($customer);
        $variant = $this->publishPurchasableVariant(quantity: 5, price: '1000', skuSuffix: 'B1C');
        $this->carts->add($customer, $variant->id, 1);

        Coupon::factory()->platform()->percent(10)->create([
            'code' => 'SAVE10',
            'currency_code' => 'SYP',
        ]);
        CheckoutCouponSession::put('SAVE10');

        $result = $this->checkout->placeOrder($customer, $address);
        $parent = $result->parentOrder->fresh(['vendorOrders']);

        $this->assertSame('SAVE10', $parent->coupon_code);
        $this->assertNotNull($parent->coupon_id);

        $vendorOrder = $parent->vendorOrders->first();
        $this->assertNotNull($vendorOrder);
        $this->assertSame(1000, $vendorOrder->items_subtotal_amount_minor);
        $this->assertSame(100, $vendorOrder->discount_amount_minor);
        $this->assertSame(1000, $vendorOrder->commission_base_amount_minor);
        $this->assertSame(
            $vendorOrder->items_subtotal_amount_minor
                - $vendorOrder->discount_amount_minor
                + $vendorOrder->shipping_amount_minor,
            $vendorOrder->grand_total_amount_minor,
        );

        $redemption = CouponRedemption::query()->where('parent_order_id', $parent->id)->first();
        $this->assertNotNull($redemption);
        $this->assertSame(CouponRedemptionStatus::Active, $redemption->status);
        $this->assertSame(100, $redemption->discount_amount_minor);
        $this->assertNull(CheckoutCouponSession::get());
    }

    public function test_parent_cancel_releases_redemption(): void
    {
        $customer = User::factory()->create();
        $address = $this->addressFor($customer);
        $variant = $this->publishPurchasableVariant(quantity: 5, price: '1000', skuSuffix: 'B1D');
        $this->carts->add($customer, $variant->id, 1);

        Coupon::factory()->platform()->percent(10)->create([
            'code' => 'SAVE10',
            'currency_code' => 'SYP',
        ]);
        CheckoutCouponSession::put('SAVE10');

        $parent = $this->checkout->placeOrder($customer, $address)->parentOrder;
        $redemption = CouponRedemption::query()->where('parent_order_id', $parent->id)->firstOrFail();
        $this->assertSame(CouponRedemptionStatus::Active, $redemption->status);

        $this->cancellations->cancelParentByCustomer($customer, $parent->fresh());

        $this->assertSame(CouponRedemptionStatus::Released, $redemption->fresh()->status);
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
            'sku' => 'CPN-B1-'.$skuSuffix.'-'.uniqid(),
            'price' => $price,
            'quantity' => $quantity,
            'translations' => [
                'ar' => ['name' => 'منتج قسيمة '.$skuSuffix],
                'en' => ['name' => 'Coupon Product '.$skuSuffix],
            ],
        ]);

        app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());
        app(ProductPublicationService::class)->publish($product->fresh());

        $product = $product->fresh(['defaultVariant']);
        $this->assertTrue(Product::query()->storefrontVisible()->whereKey($product->id)->exists());

        $variant = $product->defaultVariant;
        $this->assertNotNull($variant);
        $variant->forceFill(['quantity' => $quantity])->save();

        auth()->logout();

        return $variant->fresh(['product.store.vendor.user']);
    }
}
