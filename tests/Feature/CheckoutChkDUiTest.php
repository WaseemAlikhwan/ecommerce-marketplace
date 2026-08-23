<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\Governorate;
use App\Models\ParentOrder;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VendorOrder;
use App\Notifications\OrderPlacedCustomerNotification;
use App\Notifications\VendorOrderReceivedNotification;
use App\Services\CartService;
use App\Services\ProductImageService;
use App\Services\ProductPublicationService;
use App\Services\ProductService;
use App\Support\Locale;
use Database\Seeders\CommissionSettingSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\SyriaGeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckoutChkDUiTest extends TestCase
{
    use RefreshDatabase;

    private CartService $carts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            CurrencySeeder::class,
            SyriaGeoSeeder::class,
            CommissionSettingSeeder::class,
        ]);

        $this->carts = app(CartService::class);
    }

    public function test_guest_checkout_redirects_to_login(): void
    {
        $this->get(route('checkout.create'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_checkout_places_order_notifies_and_shows_parent_order(): void
    {
        Notification::fake();

        $customer = User::factory()->create(['preferred_locale' => 'en']);
        $address = $this->addressFor($customer);
        $variant = $this->publishPurchasableVariant(quantity: 4, skuSuffix: 'DUI');
        $vendorUser = $variant->product->store->vendor->user;

        $this->carts->add($customer, $variant->id, 2);

        $this->actingAs($customer)
            ->withCookie(Locale::COOKIE, 'en')
            ->get(route('checkout.create'))
            ->assertOk()
            ->assertSee('Confirm order', false)
            ->assertSee('Cash on delivery', false)
            ->assertDontSee($variant->sku, false);

        $response = $this->actingAs($customer)
            ->post(route('checkout.store'), [
                'address_mode' => 'existing',
                'address_id' => $address->id,
            ]);

        $parent = ParentOrder::query()->where('user_id', $customer->id)->first();
        $this->assertNotNull($parent);
        $this->assertStringStartsWith('PO-', $parent->public_code);
        $response->assertRedirect(route('account.orders.show', $parent));

        $this->actingAs($customer)
            ->get(route('account.orders.show', $parent))
            ->assertOk()
            ->assertSee($parent->public_code, false)
            ->assertSee('COD pending', false)
            ->assertSee($address->recipient_name, false)
            ->assertDontSee($variant->sku, false);

        $this->actingAs($customer)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertSee($parent->public_code, false);

        Notification::assertSentTo($customer, OrderPlacedCustomerNotification::class);
        Notification::assertSentTo($vendorUser->fresh(), VendorOrderReceivedNotification::class);
    }

    public function test_customer_cannot_view_another_customers_parent_order(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $address = $this->addressFor($owner);
        $variant = $this->publishPurchasableVariant(quantity: 3, skuSuffix: 'ISO');
        $this->carts->add($owner, $variant->id, 1);

        $this->actingAs($owner)
            ->post(route('checkout.store'), [
                'address_mode' => 'existing',
                'address_id' => $address->id,
            ])
            ->assertRedirect();

        $parent = ParentOrder::query()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($stranger)
            ->get(route('account.orders.show', $parent))
            ->assertForbidden();
    }

    public function test_vendor_sees_own_orders_only_fail_closed(): void
    {
        $customer = User::factory()->create();
        $address = $this->addressFor($customer);
        $variantA = $this->publishPurchasableVariant(quantity: 3, skuSuffix: 'VA');
        $variantB = $this->publishPurchasableVariant(quantity: 3, skuSuffix: 'VB');

        $this->carts->add($customer, $variantA->id, 1);
        $this->carts->add($customer, $variantB->id, 1);

        $this->actingAs($customer)
            ->post(route('checkout.store'), [
                'address_mode' => 'existing',
                'address_id' => $address->id,
            ])
            ->assertRedirect();

        $orderA = VendorOrder::query()->where('vendor_id', $variantA->product->store->vendor_id)->firstOrFail();
        $orderB = VendorOrder::query()->where('vendor_id', $variantB->product->store->vendor_id)->firstOrFail();

        $vendorA = $variantA->product->store->vendor->user->fresh(['vendor', 'roles']);
        $vendorB = $variantB->product->store->vendor->user->fresh(['vendor', 'roles']);

        $this->actingAs($vendorA)
            ->get(route('vendor.orders'))
            ->assertOk()
            ->assertSee($orderA->public_code, false)
            ->assertDontSee($orderB->public_code, false);

        $this->actingAs($vendorA)
            ->get(route('vendor.orders.show', $orderA))
            ->assertOk()
            ->assertSee($orderA->public_code, false);

        $this->actingAs($vendorA)
            ->get(route('vendor.orders.show', $orderB))
            ->assertNotFound();

        $this->actingAs($vendorB)
            ->get(route('vendor.orders.show', $orderA))
            ->assertNotFound();
    }

    public function test_checkout_can_create_syria_address_inline(): void
    {
        Notification::fake();

        $customer = User::factory()->create(['preferred_locale' => 'en']);
        $variant = $this->publishPurchasableVariant(quantity: 2, skuSuffix: 'NEW');
        $this->carts->add($customer, $variant->id, 1);

        $governorate = Governorate::query()->inSyria()->active()->orderBy('id')->firstOrFail();
        $city = $governorate->cities()->where('is_active', true)->orderBy('id')->firstOrFail();

        $this->actingAs($customer)
            ->post(route('checkout.store'), [
                'address_mode' => 'new',
                'recipient_name' => 'Checkout Buyer',
                'phone' => '+963912345678',
                'governorate_id' => $governorate->id,
                'city_id' => $city->id,
                'line1' => 'Street 12',
                'is_default' => '1',
            ])
            ->assertRedirect();

        $parent = ParentOrder::query()->where('user_id', $customer->id)->first();
        $this->assertNotNull($parent);

        $this->assertDatabaseHas('customer_addresses', [
            'user_id' => $customer->id,
            'recipient_name' => 'Checkout Buyer',
            'is_default' => 1,
        ]);

        $this->actingAs($customer)
            ->get(route('account.orders.show', $parent))
            ->assertOk()
            ->assertSee('Checkout Buyer', false)
            ->assertSee('Street 12', false);
    }

    public function test_arabic_checkout_page_is_rtl(): void
    {
        $customer = User::factory()->create(['preferred_locale' => 'ar']);
        $variant = $this->publishPurchasableVariant(quantity: 2, skuSuffix: 'AR');
        $this->carts->add($customer, $variant->id, 1);
        $this->addressFor($customer);

        $this->actingAs($customer)
            ->withCookie(Locale::COOKIE, 'ar')
            ->get(route('checkout.create'))
            ->assertOk()
            ->assertSee('dir="rtl"', false);
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
            'sku' => 'CHK-D-'.$skuSuffix.'-'.uniqid(),
            'price' => $price,
            'quantity' => $quantity,
            'translations' => [
                'ar' => ['name' => 'منتج واجهة طلب '.$skuSuffix],
                'en' => ['name' => 'Checkout UI Product '.$skuSuffix],
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
