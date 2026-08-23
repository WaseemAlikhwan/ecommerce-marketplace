<?php

namespace Tests\Feature;

use App\Cart\SessionCartStore;
use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\CartItem;
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

class CartC1D1Test extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_add_update_and_remove_via_http(): void
    {
        $variant = $this->publishPurchasableVariant(quantity: 5);

        $this->withCookie(Locale::COOKIE, 'en')
            ->from(route('home'))
            ->post(route('cart.items.store'), [
                'variant_id' => $variant->id,
                'quantity' => 2,
            ])
            ->assertRedirect(route('home'))
            ->assertSessionHas('status', 'Item added to cart.');

        $this->assertSame([$variant->id => 2], app(SessionCartStore::class)->lines());

        $this->withCookie(Locale::COOKIE, 'en')
            ->from(route('home'))
            ->patch(route('cart.items.update', $variant->id), [
                'quantity' => 4,
            ])
            ->assertRedirect(route('home'))
            ->assertSessionHas('status', 'Cart updated.');

        $this->assertSame([$variant->id => 4], app(SessionCartStore::class)->lines());

        $this->withCookie(Locale::COOKIE, 'en')
            ->from(route('home'))
            ->delete(route('cart.items.destroy', $variant->id))
            ->assertRedirect(route('home'))
            ->assertSessionHas('status', 'Item removed from cart.');

        $this->assertSame([], app(SessionCartStore::class)->lines());
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_authenticated_user_can_add_update_and_remove_via_http(): void
    {
        $customer = User::factory()->create(['preferred_locale' => 'en']);
        $variant = $this->publishPurchasableVariant(quantity: 6);

        $this->actingAs($customer)
            ->from(route('home'))
            ->post(route('cart.items.store'), [
                'variant_id' => $variant->id,
                'quantity' => 1,
            ])
            ->assertRedirect(route('home'))
            ->assertSessionHas('status', 'Item added to cart.');

        $this->assertDatabaseHas('cart_items', [
            'variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $this->actingAs($customer)
            ->from(route('home'))
            ->patch(route('cart.items.update', $variant->id), [
                'quantity' => 3,
            ])
            ->assertRedirect(route('home'))
            ->assertSessionHas('status', 'Cart updated.');

        $this->assertDatabaseHas('cart_items', [
            'variant_id' => $variant->id,
            'quantity' => 3,
        ]);

        $this->actingAs($customer)
            ->from(route('home'))
            ->delete(route('cart.items.destroy', $variant->id))
            ->assertRedirect(route('home'))
            ->assertSessionHas('status', 'Item removed from cart.');

        $this->assertDatabaseMissing('cart_items', ['variant_id' => $variant->id]);
    }

    public function test_add_and_update_flash_when_quantity_is_stock_capped(): void
    {
        $variant = $this->publishPurchasableVariant(quantity: 2);

        $this->withCookie(Locale::COOKIE, 'en')
            ->from(route('home'))
            ->post(route('cart.items.store'), [
                'variant_id' => $variant->id,
                'quantity' => 9,
            ])
            ->assertRedirect(route('home'))
            ->assertSessionHas('status', 'Quantity was adjusted to available stock.');

        $this->assertSame([$variant->id => 2], app(SessionCartStore::class)->lines());

        $customer = User::factory()->create(['preferred_locale' => 'en']);
        $this->actingAs($customer)
            ->post(route('cart.items.store'), [
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]);

        $this->actingAs($customer)
            ->from(route('home'))
            ->patch(route('cart.items.update', $variant->id), [
                'quantity' => 40,
            ])
            ->assertRedirect(route('home'))
            ->assertSessionHas('status', 'Quantity was adjusted to available stock.');

        $this->assertDatabaseHas('cart_items', [
            'variant_id' => $variant->id,
            'quantity' => 2,
        ]);
    }

    public function test_update_quantity_zero_removes_line_via_patch(): void
    {
        $customer = User::factory()->create(['preferred_locale' => 'en']);
        $variant = $this->publishPurchasableVariant(quantity: 4);
        app(CartService::class)->add($customer, $variant->id, 2);

        $this->actingAs($customer)
            ->from(route('home'))
            ->patch(route('cart.items.update', $variant->id), [
                'quantity' => 0,
            ])
            ->assertRedirect(route('home'))
            ->assertSessionHas('status', 'Item removed from cart.');

        $this->assertDatabaseMissing('cart_items', ['variant_id' => $variant->id]);
    }

    public function test_unavailable_variant_returns_localized_cart_error_without_sku(): void
    {
        $variant = $this->publishPurchasableVariant(quantity: 3, skuSuffix: 'HIDE');
        $sku = $variant->sku;
        $variant->product->forceFill(['status' => ProductStatus::Draft])->save();

        $this->withCookie(Locale::COOKIE, 'en')
            ->from(route('home'))
            ->post(route('cart.items.store'), [
                'variant_id' => $variant->id,
                'quantity' => 1,
            ])
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors(['cart' => 'This product is unavailable.']);

        $this->assertStringNotContainsString($sku, (string) session('errors')->first('cart'));

        $this->withCookie(Locale::COOKIE, 'ar')
            ->from(route('home'))
            ->post(route('cart.items.store'), [
                'variant_id' => $variant->id,
                'quantity' => 1,
            ])
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors(['cart' => 'هذا المنتج غير متاح.']);

        $this->assertStringNotContainsString($sku, (string) session('errors')->first('cart'));
        $this->assertSame([], app(SessionCartStore::class)->lines());
    }

    public function test_validation_rejects_invalid_payloads(): void
    {
        $this->from(route('home'))
            ->post(route('cart.items.store'), [
                'variant_id' => 0,
                'quantity' => 0,
            ])
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors(['variant_id', 'quantity']);

        $variant = $this->publishPurchasableVariant(quantity: 2);

        $this->from(route('home'))
            ->patch(route('cart.items.update', $variant->id), [
                'quantity' => -1,
            ])
            ->assertRedirect(route('home'))
            ->assertSessionHasErrors(['quantity']);
    }

    public function test_users_remain_isolated_over_http_mutations(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $variant = $this->publishPurchasableVariant(quantity: 10);

        $this->actingAs($alice)
            ->post(route('cart.items.store'), [
                'variant_id' => $variant->id,
                'quantity' => 3,
            ])
            ->assertRedirect();

        $this->actingAs($bob)
            ->post(route('cart.items.store'), [
                'variant_id' => $variant->id,
                'quantity' => 1,
            ])
            ->assertRedirect();

        $this->actingAs($bob)
            ->patch(route('cart.items.update', $variant->id), [
                'quantity' => 2,
            ])
            ->assertRedirect();

        $this->assertSame(3, CartItem::query()
            ->where('variant_id', $variant->id)
            ->whereHas('cart', fn ($q) => $q->where('user_id', $alice->id))
            ->value('quantity'));

        $this->assertSame(2, CartItem::query()
            ->where('variant_id', $variant->id)
            ->whereHas('cart', fn ($q) => $q->where('user_id', $bob->id))
            ->value('quantity'));

        $this->actingAs($bob)
            ->delete(route('cart.items.destroy', $variant->id))
            ->assertRedirect();

        $this->assertSame(1, CartItem::query()->where('variant_id', $variant->id)->count());
        $this->assertTrue(
            CartItem::query()
                ->where('variant_id', $variant->id)
                ->whereHas('cart', fn ($q) => $q->where('user_id', $alice->id))
                ->exists()
        );
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
            'sku' => 'CART-D1-'.$skuSuffix.'-'.uniqid(),
            'price' => '250',
            'quantity' => $quantity,
            'translations' => [
                'ar' => ['name' => 'منتج سلة د١ '.$skuSuffix],
                'en' => ['name' => 'Cart D1 Product '.$skuSuffix],
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
