<?php

namespace Tests\Feature;

use App\Cart\SessionCartStore;
use App\Enums\ProductStatus;
use App\Exceptions\CartException;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use App\Services\ProductImageService;
use App\Services\ProductPublicationService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CartC1ATest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_session_cart_add_update_remove(): void
    {
        $variant = $this->publishPurchasableVariant(quantity: 5);

        $cart = app(CartService::class);

        $added = $cart->add(null, $variant->id, 2);
        $this->assertSame(2, $added->quantity);
        $this->assertFalse($added->adjusted);
        $this->assertSame(
            [$variant->id => 2],
            app(SessionCartStore::class)->lines(),
        );

        $updated = $cart->update(null, $variant->id, 4);
        $this->assertSame(4, $updated->quantity);

        $cart->remove(null, $variant->id);
        $this->assertSame([], app(SessionCartStore::class)->lines());
        $this->assertCount(0, $cart->lines(null));
    }

    public function test_authenticated_cart_persists_in_database(): void
    {
        $customer = User::factory()->create();
        $variant = $this->publishPurchasableVariant(quantity: 8);

        $cart = app(CartService::class);
        $cart->add($customer, $variant->id, 3);

        $this->assertDatabaseHas('carts', ['user_id' => $customer->id]);
        $this->assertDatabaseHas('cart_items', [
            'variant_id' => $variant->id,
            'quantity' => 3,
        ]);

        $lines = $cart->lines($customer);
        $this->assertCount(1, $lines);
        $this->assertSame($variant->id, $lines->first()->variantId);
        $this->assertSame(3, $lines->first()->quantity);
    }

    public function test_multi_vendor_lines_are_allowed(): void
    {
        $customer = User::factory()->create();
        $a = $this->publishPurchasableVariant(quantity: 4, skuSuffix: 'A');
        $b = $this->publishPurchasableVariant(quantity: 4, skuSuffix: 'B');

        $this->assertNotSame($a->store_id, $b->store_id);

        $cart = app(CartService::class);
        $cart->add($customer, $a->id, 1);
        $cart->add($customer, $b->id, 2);

        $ids = $cart->lines($customer)->pluck('variantId')->sort()->values()->all();
        $this->assertSame([$a->id, $b->id], $ids);
    }

    public function test_add_and_update_cap_quantity_to_current_stock(): void
    {
        $customer = User::factory()->create();
        $variant = $this->publishPurchasableVariant(quantity: 3);

        $cart = app(CartService::class);

        $guestAdd = $cart->add(null, $variant->id, 10);
        $this->assertSame(3, $guestAdd->quantity);
        $this->assertTrue($guestAdd->adjusted);

        $authAdd = $cart->add($customer, $variant->id, 10);
        $this->assertSame(3, $authAdd->quantity);
        $this->assertTrue($authAdd->adjusted);

        $authUpdate = $cart->update($customer, $variant->id, 99);
        $this->assertSame(3, $authUpdate->quantity);
        $this->assertTrue($authUpdate->adjusted);

        $cart->add($customer, $variant->id, 2);
        $this->assertSame(3, $cart->lines($customer)->first()->quantity);
    }

    public function test_users_are_isolated_and_guest_does_not_write_database(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $variant = $this->publishPurchasableVariant(quantity: 5);

        $cart = app(CartService::class);
        $cart->add($alice, $variant->id, 2);
        $cart->add($bob, $variant->id, 1);
        $cart->add(null, $variant->id, 4);

        $this->assertSame(2, $cart->lines($alice)->first()->quantity);
        $this->assertSame(1, $cart->lines($bob)->first()->quantity);
        $this->assertSame(4, $cart->lines(null)->first()->quantity);

        $this->assertSame(2, Cart::query()->count());
        $this->assertDatabaseMissing('carts', ['user_id' => null]);
    }

    public function test_unpurchasable_variant_is_rejected_without_exposing_sku(): void
    {
        $customer = User::factory()->create();
        $variant = $this->publishPurchasableVariant(quantity: 2);
        $sku = $variant->sku;

        $variant->product->forceFill(['status' => ProductStatus::Draft])->save();

        $cart = app(CartService::class);

        try {
            $cart->add($customer, $variant->id, 1);
            $this->fail('Expected CartException');
        } catch (CartException $exception) {
            $this->assertSame(CartException::VARIANT_UNAVAILABLE, $exception->errorCode);
            $this->assertStringNotContainsString($sku, $exception->getMessage());
        }

        $live = $this->publishPurchasableVariant(quantity: 0, skuSuffix: 'OOS');

        try {
            $cart->add(null, $live->id, 1);
            $this->fail('Expected CartException for zero stock');
        } catch (CartException $exception) {
            $this->assertSame(CartException::VARIANT_UNAVAILABLE, $exception->errorCode);
            $this->assertStringNotContainsString($live->sku, $exception->getMessage());
        }
    }

    public function test_authenticated_mutations_are_transactional_and_serialize_via_row_locks(): void
    {
        $customer = User::factory()->create();
        $variant = $this->publishPurchasableVariant(quantity: 5);
        $cart = app(CartService::class);

        DB::transaction(function () use ($cart, $customer, $variant): void {
            $cart->add($customer, $variant->id, 2);
            $cart->add($customer, $variant->id, 2);
        });

        $this->assertSame(4, CartItem::query()->where('variant_id', $variant->id)->value('quantity'));

        // Nested service transactions still leave one cart row and one line.
        $this->assertSame(1, Cart::query()->where('user_id', $customer->id)->count());
        $this->assertSame(1, CartItem::query()->where('variant_id', $variant->id)->count());

        // Cap remains enforced when concurrent-style summed adds exceed stock.
        $cart->add($customer, $variant->id, 10);
        $this->assertSame(5, CartItem::query()->where('variant_id', $variant->id)->value('quantity'));
    }

    public function test_update_to_zero_removes_line(): void
    {
        $customer = User::factory()->create();
        $variant = $this->publishPurchasableVariant(quantity: 4);
        $cart = app(CartService::class);

        $cart->add($customer, $variant->id, 2);
        $result = $cart->update($customer, $variant->id, 0);

        $this->assertSame(0, $result->quantity);
        $this->assertCount(0, $cart->lines($customer));
        $this->assertDatabaseMissing('cart_items', ['variant_id' => $variant->id]);
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
            'sku' => 'CART-'.$skuSuffix.'-'.uniqid(),
            'price' => '250',
            'quantity' => $quantity,
            'translations' => [
                'ar' => ['name' => 'منتج سلة '.$skuSuffix],
                'en' => ['name' => 'Cart Product '.$skuSuffix],
            ],
        ]);

        app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());
        app(ProductPublicationService::class)->publish($product->fresh());

        $product = $product->fresh(['defaultVariant']);
        $this->assertTrue(Product::query()->storefrontVisible()->whereKey($product->id)->exists());

        $variant = $product->defaultVariant;
        $this->assertNotNull($variant);
        $variant->forceFill(['quantity' => $quantity])->save();

        return $variant->fresh();
    }
}
