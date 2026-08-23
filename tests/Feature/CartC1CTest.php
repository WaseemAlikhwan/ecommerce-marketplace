<?php

namespace Tests\Feature;

use App\Cart\CartLine;
use App\Cart\CartMergeUnavailable;
use App\Cart\CartViewLine;
use App\Cart\CartViewPresenter;
use App\Cart\SessionCartStore;
use App\Enums\ProductStatus;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use App\Services\CartViewService;
use App\Services\ProductImageService;
use App\Services\ProductPublicationService;
use App\Services\ProductService;
use App\Support\CheckedInteger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OverflowException;
use Tests\TestCase;

class CartC1CTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_cart_view_has_no_lines_or_subtotals(): void
    {
        $view = app(CartViewService::class)->view(null);

        $this->assertTrue($view->isEmpty());
        $this->assertSame([], $view->lines);
        $this->assertSame([], $view->subtotals);
        $this->assertSame(['lines' => [], 'subtotals' => []], $view->toArray());
    }

    public function test_single_currency_subtotal_uses_decimal_string_money_and_image_dimensions(): void
    {
        $customer = User::factory()->create();
        $variant = $this->publishPurchasableVariant(quantity: 10, price: '100', currencyCode: 'SYP');
        app(CartService::class)->add($customer, $variant->id, 3);

        $view = app(CartViewService::class)->view($customer, 'en');
        $payload = $view->toArray();

        $this->assertCount(1, $view->lines);
        $this->assertSame(CartViewLine::STATUS_AVAILABLE, $view->lines[0]->status);
        $this->assertSame(3, $view->lines[0]->effectiveQuantity);
        $this->assertSame(3, $view->lines[0]->requestedQuantity);
        $this->assertSame([], $view->lines[0]->selection);
        $this->assertSame(400, $view->lines[0]->imageWidth);
        $this->assertSame(400, $view->lines[0]->imageHeight);

        $this->assertCount(1, $view->subtotals);
        $this->assertSame('SYP', $view->subtotals[0]->currencyCode);
        $this->assertSame('300', $view->subtotals[0]->total['amount_minor']);
        $this->assertSame('100', $view->lines[0]->unitPrice['amount_minor']);
        $this->assertSame('300', $view->lines[0]->lineTotal['amount_minor']);
        $this->assertIsString($payload['lines'][0]['unit_price']['amount_minor']);
        $this->assertArrayNotHasKey('sku', $payload['lines'][0]);
        $this->assertArrayNotHasKey('quantity_available', $payload['lines'][0]);
        $this->assertArrayNotHasKey('stock', $payload['lines'][0]);
    }

    public function test_mixed_currency_subtotals_are_separate_without_conversion(): void
    {
        $customer = User::factory()->create();
        $syp = $this->publishPurchasableVariant(quantity: 5, price: '250', currencyCode: 'SYP', skuSuffix: 'SYP');
        $usd = $this->publishPurchasableVariant(quantity: 5, price: '10.00', currencyCode: 'USD', skuSuffix: 'USD');
        $cart = app(CartService::class);

        $cart->add($customer, $syp->id, 2);
        $cart->add($customer, $usd->id, 1);

        $view = app(CartViewService::class)->view($customer);
        $subtotals = collect($view->subtotals)->keyBy('currencyCode');

        $this->assertCount(2, $view->subtotals);
        $this->assertSame('500', $subtotals['SYP']->total['amount_minor']);
        $this->assertSame('1000', $subtotals['USD']->total['amount_minor']);
        $this->assertArrayNotHasKey('grand_total', $view->toArray());
        $this->assertArrayNotHasKey('converted_total', $view->toArray());
    }

    public function test_non_visible_products_return_generic_unavailable_without_catalog_details(): void
    {
        $customer = User::factory()->create();
        $keep = $this->publishPurchasableVariant(quantity: 4, price: '50', skuSuffix: 'KEEP');
        $drop = $this->publishPurchasableVariant(quantity: 4, price: '50', skuSuffix: 'DROP');
        $cart = app(CartService::class);

        $cart->add($customer, $keep->id, 1);
        $cart->add($customer, $drop->id, 2);
        $hiddenName = (string) $drop->product->translations->firstWhere('locale', 'en')?->name;
        $hiddenSlug = (string) $drop->product->slug;
        $hiddenStore = (string) $drop->product->store->name;
        $drop->product->forceFill(['status' => ProductStatus::Draft])->save();

        $view = app(CartViewService::class)->view($customer);
        $payload = $view->toArray();

        $this->assertCount(2, $view->lines);
        $byVariant = collect($view->lines)->keyBy('variantId');
        $hidden = $byVariant[$drop->id];

        $this->assertSame(CartViewLine::STATUS_AVAILABLE, $byVariant[$keep->id]->status);
        $this->assertSame(CartViewLine::STATUS_UNAVAILABLE, $hidden->status);
        $this->assertSame(CartMergeUnavailable::NOT_PURCHASABLE, $hidden->unavailableReason);
        $this->assertSame(0, $hidden->productId);
        $this->assertSame('', $hidden->productSlug);
        $this->assertSame('', $hidden->productName);
        $this->assertSame('', $hidden->storeName);
        $this->assertSame('', $hidden->storeSlug);
        $this->assertNull($hidden->imageUrl);
        $this->assertNull($hidden->imageAlt);
        $this->assertNull($hidden->imageWidth);
        $this->assertNull($hidden->imageHeight);
        $this->assertNull($hidden->unitPrice);
        $this->assertNull($hidden->lineTotal);
        $this->assertSame([], $hidden->selection);
        $this->assertSame(0, $hidden->effectiveQuantity);

        $this->assertCount(1, $view->subtotals);
        $this->assertSame('50', $view->subtotals[0]->total['amount_minor']);

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($hiddenName, $json);
        $this->assertStringNotContainsString($hiddenSlug, $json);
        $this->assertStringNotContainsString($hiddenStore, $json);
        $this->assertStringNotContainsString($drop->sku, $json);

        $this->assertDatabaseHas('cart_items', [
            'variant_id' => $drop->id,
            'quantity' => 2,
        ]);
        $this->assertSame(2, CartItem::query()->count());
    }

    public function test_visible_out_of_stock_lines_keep_public_details_without_sku_or_stock(): void
    {
        $customer = User::factory()->create();
        $variant = $this->publishPurchasableVariant(quantity: 3, price: '75', skuSuffix: 'OOS');
        app(CartService::class)->add($customer, $variant->id, 2);
        $variant->forceFill(['quantity' => 0])->save();

        $view = app(CartViewService::class)->view($customer, 'en');
        $line = $view->lines[0];
        $payload = $line->toArray();

        $this->assertSame(CartViewLine::STATUS_UNAVAILABLE, $line->status);
        $this->assertSame(CartMergeUnavailable::OUT_OF_STOCK, $line->unavailableReason);
        $this->assertSame((int) $variant->product_id, $line->productId);
        $this->assertSame((string) $variant->product->slug, $line->productSlug);
        $this->assertNotSame('', $line->productName);
        $this->assertNotSame('', $line->storeName);
        $this->assertNotNull($line->unitPrice);
        $this->assertSame('75', $line->unitPrice['amount_minor']);
        $this->assertNull($line->lineTotal);
        $this->assertSame(0, $line->effectiveQuantity);
        $this->assertSame(400, $line->imageWidth);
        $this->assertSame(400, $line->imageHeight);
        $this->assertSame([], $view->subtotals);

        $this->assertArrayNotHasKey('sku', $payload);
        $this->assertArrayNotHasKey('stock', $payload);
        $this->assertDatabaseHas('cart_items', [
            'variant_id' => $variant->id,
            'quantity' => 2,
        ]);
    }

    public function test_stock_short_lines_use_adjusted_effective_quantity_without_mutating_cart(): void
    {
        $guestVariant = $this->publishPurchasableVariant(quantity: 5, price: '20', skuSuffix: 'ADJ');
        $cart = app(CartService::class);
        $cart->add(null, $guestVariant->id, 4);
        $guestVariant->forceFill(['quantity' => 2])->save();

        $view = app(CartViewService::class)->view(null);
        $line = $view->lines[0];

        $this->assertSame(CartViewLine::STATUS_ADJUSTED, $line->status);
        $this->assertSame(4, $line->requestedQuantity);
        $this->assertSame(2, $line->effectiveQuantity);
        $this->assertSame('40', $line->lineTotal['amount_minor']);
        $this->assertSame('40', $view->subtotals[0]->total['amount_minor']);

        $this->assertSame([$guestVariant->id => 4], app(SessionCartStore::class)->lines());
    }

    public function test_variable_selection_labels_are_localized_without_sku(): void
    {
        $customer = User::factory()->create();
        $variant = $this->publishPurchasableVariableVariant();
        app(CartService::class)->add($customer, $variant->id, 1);

        $en = app(CartViewService::class)->view($customer, 'en')->lines[0];
        $ar = app(CartViewService::class)->view($customer, 'ar')->lines[0];

        $this->assertCount(1, $en->selection);
        $this->assertSame('hue', $en->selection[0]['attribute_code']);
        $this->assertSame('crimson', $en->selection[0]['value_code']);
        $this->assertNotSame('', $en->selection[0]['attribute_name']);
        $this->assertNotSame('', $en->selection[0]['value_name']);
        $this->assertSame($en->selection[0]['attribute_code'], $ar->selection[0]['attribute_code']);
        $this->assertNotSame($en->selection[0]['attribute_name'], $ar->selection[0]['attribute_name']);
        $this->assertNotSame($en->selection[0]['value_name'], $ar->selection[0]['value_name']);

        $json = json_encode($en->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($variant->sku, $json);
        $this->assertArrayNotHasKey('sku', $en->toArray());
    }

    public function test_presenter_executes_zero_queries_and_remains_side_effect_free(): void
    {
        $customer = User::factory()->create();
        $variant = $this->publishPurchasableVariableVariant(quantity: 4);
        app(CartService::class)->add($customer, $variant->id, 3);
        $variant->forceFill(['quantity' => 1])->save();

        $service = app(CartViewService::class);
        $loaded = ProductVariant::query()
            ->with([
                'product.translations',
                'product.currency',
                'product.store',
                'product.primaryImage.translations',
                'attributeValueLinks.productAttributeValue.attributeValue.translations',
                'attributeValueLinks.productAttributeValue.attributeValue.attribute.translations',
            ])
            ->whereKey($variant->id)
            ->get()
            ->keyBy('id');

        $cartLines = collect([new CartLine($variant->id, 3)]);
        $visibleIds = [(int) $variant->product_id => true];

        DB::flushQueryLog();
        DB::enableQueryLog();
        $view = app(CartViewPresenter::class)->present($cartLines, $loaded, $visibleIds, 'en');
        $this->assertSame([], DB::getQueryLog());

        $this->assertSame(CartViewLine::STATUS_ADJUSTED, $view->lines[0]->status);
        $this->assertSame(1, $view->lines[0]->effectiveQuantity);
        $this->assertNotSame([], $view->lines[0]->selection);

        $this->assertDatabaseHas('cart_items', [
            'variant_id' => $variant->id,
            'quantity' => 3,
        ]);
        $this->assertSame(1, CartItem::query()->count());

        // Service path must also leave stored qty untouched after read-time adjustment.
        $serviceView = $service->view($customer, 'en');
        $this->assertSame(CartViewLine::STATUS_ADJUSTED, $serviceView->lines[0]->status);
        $this->assertDatabaseHas('cart_items', [
            'variant_id' => $variant->id,
            'quantity' => 3,
        ]);
    }

    public function test_guest_and_auth_views_do_not_expose_sku_or_exact_stock_fields(): void
    {
        $customer = User::factory()->create();
        $variant = $this->publishPurchasableVariant(quantity: 7, price: '15', skuSuffix: 'PRIV');
        app(CartService::class)->add($customer, $variant->id, 1);

        $json = json_encode(app(CartViewService::class)->view($customer)->toArray(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString($variant->sku, $json);
        $this->assertStringNotContainsString('"stock"', $json);
        $this->assertStringNotContainsString('quantity_available', $json);
        $this->assertStringNotContainsString('seller_id', $json);
        $this->assertStringNotContainsString('vendor_id', $json);
    }

    public function test_checked_integer_rejects_overflow(): void
    {
        $this->expectException(OverflowException::class);
        CheckedInteger::multiply(PHP_INT_MAX, 2);
    }

    private function publishPurchasableVariant(
        int $quantity,
        string $price = '250',
        string $currencyCode = 'SYP',
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
            'currency_code' => $currencyCode,
            'sku' => 'CART-C-'.$skuSuffix.'-'.uniqid(),
            'price' => $price,
            'quantity' => $quantity,
            'translations' => [
                'ar' => ['name' => 'منتج عرض '.$skuSuffix],
                'en' => ['name' => 'View Product '.$skuSuffix],
            ],
        ]);

        app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());
        app(ProductPublicationService::class)->publish($product->fresh());

        $product = $product->fresh(['defaultVariant', 'store', 'translations']);
        $this->assertTrue(Product::query()->storefrontVisible()->whereKey($product->id)->exists());

        $variant = $product->defaultVariant;
        $this->assertNotNull($variant);
        $variant->forceFill(['quantity' => $quantity])->save();

        auth()->logout();

        return $variant->fresh(['product.store', 'product.translations']);
    }

    private function publishPurchasableVariableVariant(int $quantity = 2): ProductVariant
    {
        Storage::fake('public');

        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);
        $category = Category::factory()->create(['is_active' => true]);

        $color = Attribute::factory()->create(['code' => 'hue', 'is_active' => true]);
        $red = AttributeValue::factory()->for($color)->create(['code' => 'crimson', 'is_active' => true]);

        $product = app(ProductService::class)->createVariableDraft($vendor->vendor->store, [
            'type' => 'variable',
            'category_id' => $category->id,
            'currency_code' => 'SYP',
            'translations' => [
                'ar' => ['name' => 'منتج متغير'],
                'en' => ['name' => 'Variable Cart Product'],
            ],
            'attributes' => [
                ['attribute_id' => $color->id, 'value_ids' => [$red->id]],
            ],
            'variants' => [
                [
                    'value_ids' => [$red->id],
                    'sku' => 'CART-VAR-'.uniqid(),
                    'price' => '90',
                    'quantity' => $quantity,
                    'is_default' => true,
                ],
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
