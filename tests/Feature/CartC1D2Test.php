<?php

namespace Tests\Feature;

use App\Cart\CartMergeResult;
use App\Cart\CartMergeUnavailable;
use App\Cart\SessionCartStore;
use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use App\Services\ProductImageService;
use App\Services\ProductPublicationService;
use App\Services\ProductService;
use App\Support\Locale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CartC1D2Test extends TestCase
{
    use RefreshDatabase;

    public function test_empty_cart_page_and_nav_cart_affordance(): void
    {
        $this->withCookie(Locale::COOKIE, 'en')
            ->get(route('cart.show'))
            ->assertOk()
            ->assertSee('Your cart is empty', false)
            ->assertSee('Browse products', false)
            ->assertSee(route('cart.show', absolute: false), false)
            ->assertDontSee('Wishlist', false)
            ->assertDontSee('CheckoutService', false);
    }

    public function test_cart_page_shows_lines_subtotals_and_disabled_checkout_for_auth(): void
    {
        $customer = User::factory()->create(['preferred_locale' => 'en']);
        $variant = $this->publishPurchasableVariant(quantity: 5, skuSuffix: 'UI');
        app(CartService::class)->add($customer, $variant->id, 2);

        $this->actingAs($customer)
            ->get(route('cart.show'))
            ->assertOk()
            ->assertSee('Cart D2 Product UI', false)
            ->assertSee('Subtotals by currency', false)
            ->assertSee('Continue to checkout', false)
            ->assertSee('Checkout opens in a later phase', false)
            ->assertSee('disabled', false)
            ->assertDontSee($variant->sku, false)
            ->assertDontSee('quantity_available', false);
    }

    public function test_pdp_add_form_posts_to_c1d1_route_and_cart_page_lists_line(): void
    {
        $variant = $this->publishPurchasableVariant(quantity: 4, skuSuffix: 'PDP');

        $this->from(route('storefront.product', $variant->product->slug))
            ->post(route('cart.items.store'), [
                'variant_id' => $variant->id,
                'quantity' => 2,
            ])
            ->assertRedirect(route('storefront.product', $variant->product->slug));

        $this->assertSame([$variant->id => 2], app(SessionCartStore::class)->lines());

        $this->withCookie(Locale::COOKIE, 'en')
            ->get(route('cart.show'))
            ->assertOk()
            ->assertSee('Cart D2 Product PDP', false)
            ->assertSee('Update', false)
            ->assertSee('Remove', false);
    }

    public function test_cart_page_marks_adjusted_and_generic_unavailable_without_leaking_hidden_catalog(): void
    {
        $customer = User::factory()->create(['preferred_locale' => 'en']);
        $ok = $this->publishPurchasableVariant(quantity: 5, skuSuffix: 'OK');
        $short = $this->publishPurchasableVariant(quantity: 5, skuSuffix: 'ADJ');
        $hidden = $this->publishPurchasableVariant(quantity: 5, skuSuffix: 'HID');
        $cart = app(CartService::class);

        $cart->add($customer, $ok->id, 1);
        $cart->add($customer, $short->id, 4);
        $cart->add($customer, $hidden->id, 1);

        $short->forceFill(['quantity' => 1])->save();
        $hiddenSlug = (string) $hidden->product->slug;
        $hidden->product->forceFill(['status' => ProductStatus::Draft])->save();

        $this->actingAs($customer)
            ->get(route('cart.show'))
            ->assertOk()
            ->assertSee('Quantity adjusted from 4 to 1 to match available stock.', false)
            ->assertSee('This item is no longer available.', false)
            ->assertDontSee($hiddenSlug, false)
            ->assertDontSee($hidden->sku, false);
    }

    public function test_cart_page_consumes_merge_flash_once(): void
    {
        $customer = User::factory()->create(['preferred_locale' => 'en']);
        $variant = $this->publishPurchasableVariant(quantity: 2, skuSuffix: 'MRG');
        app(CartService::class)->add($customer, $variant->id, 2);

        $flash = [
            'kept' => [['variant_id' => $variant->id, 'quantity' => 1]],
            'adjusted' => [[
                'variant_id' => $variant->id,
                'from_quantity' => 5,
                'to_quantity' => 1,
            ]],
            'unavailable' => [[
                'variant_id' => 999,
                'reason' => CartMergeUnavailable::OUT_OF_STOCK,
            ]],
        ];

        $this->actingAs($customer)
            ->withSession([CartMergeResult::FLASH_KEY => $flash])
            ->get(route('cart.show'))
            ->assertOk()
            ->assertSee('Some quantities were updated', false)
            ->assertSee('Some items could not be kept', false)
            ->assertSee('An item was removed because it is out of stock.', false);

        $this->actingAs($customer)
            ->get(route('cart.show'))
            ->assertOk()
            ->assertDontSee('Some quantities were updated', false)
            ->assertDontSee('Some items could not be kept', false);
    }

    public function test_guest_sees_login_cta_not_checkout_control(): void
    {
        $variant = $this->publishPurchasableVariant(quantity: 3, skuSuffix: 'CTA');
        app(CartService::class)->add(null, $variant->id, 1);

        $this->withCookie(Locale::COOKIE, 'en')
            ->get(route('cart.show'))
            ->assertOk()
            ->assertSee('Log in to continue', false)
            ->assertSee(route('login', absolute: false), false)
            ->assertDontSee('Continue to checkout', false);
    }

    public function test_arabic_cart_page_is_rtl_with_localized_copy(): void
    {
        $variant = $this->publishPurchasableVariant(quantity: 3, skuSuffix: 'AR');
        app(CartService::class)->add(null, $variant->id, 1);

        $this->withCookie(Locale::COOKIE, 'ar')
            ->get(route('cart.show'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('السلة', false)
            ->assertSee('تسجيل الدخول للمتابعة', false);
    }

    private function publishPurchasableVariant(int $quantity, string $skuSuffix = '1'): ProductVariant
    {
        Storage::fake('public');

        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);

        $category = Category::factory()->create(['is_active' => true]);
        $brand = Brand::factory()->create(['is_active' => true]);

        $product = app(ProductService::class)->createSimpleDraft($vendor->vendor->store, [
            'type' => 'simple',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'currency_code' => 'SYP',
            'sku' => 'CART-D2-'.$skuSuffix.'-'.uniqid(),
            'price' => '250',
            'quantity' => $quantity,
            'translations' => [
                'ar' => ['name' => 'منتج سلة د٢ '.$skuSuffix],
                'en' => ['name' => 'Cart D2 Product '.$skuSuffix],
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

        return $variant->fresh(['product']);
    }
}
