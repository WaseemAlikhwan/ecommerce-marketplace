<?php

namespace Tests\Feature;

use App\Cart\CartMergeResult;
use App\Cart\CartMergeTransactionHook;
use App\Cart\CartMergeUnavailable;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class CartC1BTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_session_merge_is_noop_and_clears_session(): void
    {
        $customer = User::factory()->create();
        $cart = app(CartService::class);

        $result = $cart->mergeGuestCart($customer);

        $this->assertTrue($result->isEmpty());
        $this->assertSame([], app(SessionCartStore::class)->lines());
        $this->assertCount(0, $cart->lines($customer));
    }

    public function test_empty_db_receives_guest_lines(): void
    {
        $customer = User::factory()->create();
        $variant = $this->publishPurchasableVariant(quantity: 5);
        $cart = app(CartService::class);

        $cart->add(null, $variant->id, 2);
        $result = $cart->mergeGuestCart($customer);

        $this->assertCount(1, $result->kept);
        $this->assertSame($variant->id, $result->kept[0]->variantId);
        $this->assertSame(2, $result->kept[0]->quantity);
        $this->assertSame([], $result->adjusted);
        $this->assertSame([], $result->unavailable);
        $this->assertSame([], app(SessionCartStore::class)->lines());
        $this->assertSame(2, $cart->lines($customer)->first()->quantity);
    }

    public function test_overlap_sums_and_caps_to_stock_with_adjustment_report(): void
    {
        $customer = User::factory()->create();
        $variant = $this->publishPurchasableVariant(quantity: 5);
        $cart = app(CartService::class);

        $cart->add($customer, $variant->id, 2);
        $cart->add(null, $variant->id, 4);

        $result = $cart->mergeGuestCart($customer);

        $this->assertSame(5, $cart->lines($customer)->first()->quantity);
        $this->assertCount(1, $result->adjusted);
        $this->assertSame($variant->id, $result->adjusted[0]->variantId);
        $this->assertSame(6, $result->adjusted[0]->fromQuantity);
        $this->assertSame(5, $result->adjusted[0]->toQuantity);
        $this->assertSame([], app(SessionCartStore::class)->lines());
    }

    public function test_unavailable_guest_line_is_reported_and_dropped(): void
    {
        $customer = User::factory()->create();
        $variant = $this->publishPurchasableVariant(quantity: 3, skuSuffix: 'U');
        $cart = app(CartService::class);

        $cart->add(null, $variant->id, 2);
        $variant->product->forceFill(['status' => ProductStatus::Draft])->save();

        $result = $cart->mergeGuestCart($customer);

        $this->assertSame([], $result->kept);
        $this->assertCount(1, $result->unavailable);
        $this->assertSame($variant->id, $result->unavailable[0]->variantId);
        $this->assertSame(CartMergeUnavailable::NOT_PURCHASABLE, $result->unavailable[0]->reason);
        $this->assertDatabaseMissing('cart_items', ['variant_id' => $variant->id]);
        $this->assertSame([], app(SessionCartStore::class)->lines());
    }

    public function test_out_of_stock_guest_line_is_unavailable(): void
    {
        $customer = User::factory()->create();
        $variant = $this->publishPurchasableVariant(quantity: 2, skuSuffix: 'Z');
        $cart = app(CartService::class);

        $cart->add(null, $variant->id, 1);
        $variant->forceFill(['quantity' => 0])->save();

        $result = $cart->mergeGuestCart($customer);

        $this->assertCount(1, $result->unavailable);
        $this->assertSame(CartMergeUnavailable::OUT_OF_STOCK, $result->unavailable[0]->reason);
        $this->assertSame([], app(SessionCartStore::class)->lines());
    }

    public function test_multi_currency_guest_lines_merge_without_conversion(): void
    {
        $customer = User::factory()->create();
        $syp = $this->publishPurchasableVariant(quantity: 4, skuSuffix: 'SYP', currencyCode: 'SYP');
        $usd = $this->publishPurchasableVariant(quantity: 4, skuSuffix: 'USD', currencyCode: 'USD');
        $cart = app(CartService::class);

        $cart->add(null, $syp->id, 1);
        $cart->add(null, $usd->id, 2);

        $result = $cart->mergeGuestCart($customer);

        $this->assertCount(2, $result->kept);
        $this->assertSame([], $result->adjusted);
        $this->assertSame([], $result->unavailable);

        $lines = $cart->lines($customer)->keyBy('variantId');
        $this->assertSame(1, $lines[$syp->id]->quantity);
        $this->assertSame(2, $lines[$usd->id]->quantity);
        $this->assertSame('SYP', $syp->product->currency_code);
        $this->assertSame('USD', $usd->product->currency_code);
        $this->assertSame([], app(SessionCartStore::class)->lines());
    }

    public function test_second_merge_with_empty_session_is_idempotent(): void
    {
        $customer = User::factory()->create();
        $variant = $this->publishPurchasableVariant(quantity: 5);
        $cart = app(CartService::class);

        $cart->add(null, $variant->id, 3);
        $cart->mergeGuestCart($customer);

        $second = $cart->mergeGuestCart($customer);

        $this->assertTrue($second->isEmpty());
        $this->assertSame(3, $cart->lines($customer)->first()->quantity);
        $this->assertSame(1, CartItem::query()->where('variant_id', $variant->id)->count());
        $this->assertSame([], app(SessionCartStore::class)->lines());
    }

    public function test_login_http_merges_guest_cart_and_flashes_json_safe_payload(): void
    {
        $customer = User::factory()->create([
            'email' => 'buyer@example.test',
            'password' => 'password',
        ]);
        $variant = $this->publishPurchasableVariant(quantity: 6, skuSuffix: 'L');
        $cart = app(CartService::class);

        $cart->add(null, $variant->id, 2);
        $this->assertNotSame([], app(SessionCartStore::class)->lines());

        $response = $this->post('/login', [
            'email' => 'buyer@example.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('cart.show', absolute: false));
        $this->assertAuthenticatedAs($customer);
        $this->assertSame([], app(SessionCartStore::class)->lines());
        $this->assertSame(2, $cart->lines($customer->fresh())->first()->quantity);

        $response->assertSessionHas(CartMergeResult::FLASH_KEY);
        $payload = session(CartMergeResult::FLASH_KEY);
        $this->assertIsArray($payload);
        $this->assertSame(
            [
                'kept' => [[
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                ]],
                'adjusted' => [],
                'unavailable' => [],
            ],
            $payload,
        );
        $this->assertNotFalse(json_encode($payload));
    }

    public function test_register_http_merges_guest_cart_and_flashes_json_safe_payload(): void
    {
        $variant = $this->publishPurchasableVariant(quantity: 4, skuSuffix: 'R');
        $cart = app(CartService::class);

        $cart->add(null, $variant->id, 3);
        $this->assertNotSame([], app(SessionCartStore::class)->lines());

        $response = $this->post('/register', [
            'name' => 'Cart Buyer',
            'email' => 'cart-buyer@example.test',
            'phone' => '+963911223344',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('cart.show', absolute: false));
        $this->assertAuthenticated();
        $this->assertSame([], app(SessionCartStore::class)->lines());

        $user = User::query()->where('email', 'cart-buyer@example.test')->firstOrFail();
        $this->assertSame(3, $cart->lines($user)->first()->quantity);

        $response->assertSessionHas(CartMergeResult::FLASH_KEY);
        $payload = session(CartMergeResult::FLASH_KEY);
        $this->assertSame(
            [
                'kept' => [[
                    'variant_id' => $variant->id,
                    'quantity' => 3,
                ]],
                'adjusted' => [],
                'unavailable' => [],
            ],
            $payload,
        );
        $this->assertNotFalse(json_encode($payload));
    }

    public function test_exception_inside_merge_transaction_rolls_back_db_and_keeps_guest_session(): void
    {
        $customer = User::factory()->create();
        $variant = $this->publishPurchasableVariant(quantity: 5, skuSuffix: 'F');
        $cart = app(CartService::class);

        $cart->add($customer, $variant->id, 1);
        $cart->add(null, $variant->id, 2);
        $this->assertSame([$variant->id => 2], app(SessionCartStore::class)->lines());
        $this->assertSame(1, $cart->lines($customer)->first()->quantity);

        $this->app->instance(CartMergeTransactionHook::class, new class extends CartMergeTransactionHook
        {
            public function beforeCommit(): void
            {
                throw new RuntimeException('forced merge failure');
            }
        });

        try {
            app(CartService::class)->mergeGuestCart($customer);
            $this->fail('Expected merge to throw');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced merge failure', $exception->getMessage());
        } finally {
            $this->app->instance(CartMergeTransactionHook::class, new CartMergeTransactionHook);
        }

        $this->assertSame([$variant->id => 2], app(SessionCartStore::class)->lines());
        $this->assertSame(1, CartItem::query()->where('variant_id', $variant->id)->value('quantity'));
        $this->assertSame(1, app(CartService::class)->lines($customer)->first()->quantity);
    }

    private function publishPurchasableVariant(
        int $quantity,
        string $skuSuffix = '1',
        string $currencyCode = 'SYP',
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
            'currency_code' => $currencyCode,
            'sku' => 'CART-B-'.$skuSuffix.'-'.uniqid(),
            'price' => $currencyCode === 'USD' ? '10.00' : '250',
            'quantity' => $quantity,
            'translations' => [
                'ar' => ['name' => 'منتج دمج '.$skuSuffix],
                'en' => ['name' => 'Merge Product '.$skuSuffix],
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
