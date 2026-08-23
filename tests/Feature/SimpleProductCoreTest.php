<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SimpleProductCoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function productPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'type' => 'simple',
            'slug' => null,
            'category_id' => null,
            'brand_id' => null,
            'currency_code' => null,
            'sku' => 'SKU-001',
            'price' => '185000',
            'compare_at_price' => null,
            'quantity' => 5,
            'translations' => [
                'ar' => [
                    'name' => 'منتج تجريبي',
                    'short_description' => null,
                    'description' => null,
                ],
                'en' => [
                    'name' => 'Demo Product',
                    'short_description' => null,
                    'description' => null,
                ],
            ],
        ], $overrides);
    }

    public function test_guest_and_customer_cannot_manage_vendor_products(): void
    {
        $customer = User::factory()->create();
        $vendor = $this->createVendorUser();
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);

        $this->get(route('vendor.products'))->assertRedirect('/login');
        $this->get(route('vendor.products.create'))->assertRedirect('/login');
        $this->post(route('vendor.products.store'), $this->productPayload())->assertRedirect('/login');

        $this->actingAs($customer)->get(route('vendor.products'))->assertForbidden();
        $this->actingAs($customer)->get(route('vendor.products.create'))->assertForbidden();
        $this->actingAs($customer)->post(route('vendor.products.store'), $this->productPayload())->assertForbidden();
        $this->actingAs($customer)->get(route('vendor.products.edit', $product))->assertForbidden();
        $this->actingAs($customer)->put(route('vendor.products.update', $product), $this->productPayload())->assertForbidden();
        $this->actingAs($customer)->post(route('vendor.products.archive', $product))->assertForbidden();
    }

    public function test_staff_cannot_create_products_on_behalf_of_vendors(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertFalse($admin->can('create', Product::class));
        $this->actingAs($admin)->get(route('vendor.products'))->assertForbidden();
        $this->actingAs($admin)->post(route('vendor.products.store'), $this->productPayload())->assertForbidden();
    }

    public function test_vendor_can_create_simple_product_atomically_with_default_variant(): void
    {
        $vendor = $this->createVendorUser();
        $store = $vendor->vendor->store;

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'abc-100',
                'price' => '185000',
                'quantity' => 3,
            ]))
            ->assertRedirect();

        $product = Product::query()->where('store_id', $store->id)->firstOrFail();
        $variant = $product->defaultVariant;

        $this->assertSame(ProductType::Simple, $product->type);
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertSame($store->id, $product->store_id);
        $this->assertSame('SYP', $product->currency_code);
        $this->assertNotNull($variant);
        $this->assertSame($variant->id, $product->default_variant_id);
        $this->assertSame(ProductVariant::DEFAULT_COMBINATION_KEY, $variant->combination_key);
        $this->assertSame('ABC-100', $variant->sku);
        $this->assertSame($store->id, $variant->store_id);
        $this->assertSame(185000, $variant->price_amount_minor);
        $this->assertSame(3, $variant->quantity);
        $this->assertSame(1, $product->variants()->count());
    }

    public function test_schema_keeps_price_sku_stock_off_product_and_currency_off_variant(): void
    {
        $this->assertTrue(Schema::hasTable('products'));
        $this->assertTrue(Schema::hasTable('product_translations'));
        $this->assertTrue(Schema::hasTable('product_variants'));

        $this->assertFalse(Schema::hasColumn('products', 'vendor_id'));
        $this->assertFalse(Schema::hasColumn('products', 'price'));
        $this->assertFalse(Schema::hasColumn('products', 'price_amount_minor'));
        $this->assertFalse(Schema::hasColumn('products', 'sku'));
        $this->assertFalse(Schema::hasColumn('products', 'quantity'));
        $this->assertFalse(Schema::hasColumn('products', 'stock'));
        $this->assertFalse(Schema::hasColumn('product_variants', 'currency_code'));
        $this->assertFalse(Schema::hasColumn('product_variants', 'currency'));

        $this->assertTrue(Schema::hasColumn('products', 'default_variant_id'));
        $this->assertTrue(Schema::hasColumn('products', 'primary_image_id'));
        $this->assertFalse(Schema::hasColumn('product_variants', 'is_default'));
        $this->assertTrue(Schema::hasTable('product_images'));
        $this->assertTrue(Schema::hasTable('product_image_translations'));
        $this->assertTrue(Schema::hasTable('product_attributes'));
        $this->assertTrue(Schema::hasTable('product_attribute_values'));
        $this->assertTrue(Schema::hasTable('product_variant_attribute_values'));
        $this->assertTrue(Schema::hasTable('carts'));
        $this->assertTrue(Schema::hasTable('cart_items'));
        $this->assertFalse(Schema::hasTable('orders'));
        $this->assertFalse(Schema::hasTable('inventory_movements'));
    }

    public function test_composite_fk_rejects_mismatched_product_variant_store(): void
    {
        $owner = $this->createVendorUser();
        $other = $this->createVendorUser();
        $product = Product::factory()->create(['store_id' => $owner->vendor->store->id]);

        $this->expectException(QueryException::class);

        DB::table('product_variants')->insert([
            'product_id' => $product->id,
            'store_id' => $other->vendor->store->id,
            'sku' => 'MISMATCH-1',
            'combination_key' => 'other',
            'price_amount_minor' => 100,
            'compare_at_amount_minor' => null,
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_vendor_cannot_access_another_stores_product(): void
    {
        $owner = $this->createVendorUser();
        $other = $this->createVendorUser();
        $product = Product::factory()->create(['store_id' => $owner->vendor->store->id]);

        $this->assertFalse($other->can('view', $product));
        $this->assertFalse($other->can('update', $product));
        $this->assertFalse($other->can('archive', $product));

        $this->actingAs($other)->get(route('vendor.products.edit', $product))->assertForbidden();
        $this->actingAs($other)->put(route('vendor.products.update', $product), $this->productPayload([
            'sku' => 'OTHER-SKU',
        ]))->assertForbidden();
        $this->actingAs($other)->post(route('vendor.products.archive', $product))->assertForbidden();
    }

    public function test_sku_unique_per_store_and_allowed_across_stores(): void
    {
        $vendorA = $this->createVendorUser();
        $vendorB = $this->createVendorUser();

        $this->actingAs($vendorA)
            ->post(route('vendor.products.store'), $this->productPayload(['sku' => 'SHARED-SKU']))
            ->assertRedirect();

        $this->actingAs($vendorA)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'shared-sku',
                'translations' => [
                    'en' => ['name' => 'Second'],
                    'ar' => ['name' => ''],
                ],
            ]))
            ->assertSessionHasErrors('sku');

        $this->actingAs($vendorB)
            ->post(route('vendor.products.store'), $this->productPayload(['sku' => 'SHARED-SKU']))
            ->assertRedirect();

        $this->assertSame(2, ProductVariant::query()->where('sku', 'SHARED-SKU')->count());
    }

    public function test_soft_deleted_sku_remains_reserved(): void
    {
        $vendor = $this->createVendorUser();

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload(['sku' => 'KEEP-SKU']))
            ->assertRedirect();

        $product = Product::query()->where('store_id', $vendor->vendor->store->id)->firstOrFail();

        $this->actingAs($vendor)
            ->post(route('vendor.products.archive', $product))
            ->assertRedirect(route('vendor.products'));

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'keep-sku',
                'translations' => [
                    'en' => ['name' => 'Reuse attempt'],
                    'ar' => ['name' => ''],
                ],
            ]))
            ->assertSessionHasErrors('sku');
    }

    public function test_product_defaults_to_store_currency_and_accepts_active_usd(): void
    {
        $vendor = $this->createVendorUser();
        $vendor->vendor->store->update(['default_currency_code' => 'SYP']);

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'currency_code' => null,
                'sku' => 'CUR-SYP',
            ]))
            ->assertRedirect();

        $sypProduct = Product::query()->whereHas('defaultVariant', fn ($q) => $q->where('sku', 'CUR-SYP'))->firstOrFail();
        $this->assertSame('SYP', $sypProduct->currency_code);

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'currency_code' => 'usd',
                'sku' => 'CUR-USD',
                'price' => '49.99',
                'translations' => [
                    'en' => ['name' => 'USD Product'],
                    'ar' => ['name' => ''],
                ],
            ]))
            ->assertRedirect();

        $usdProduct = Product::query()->where('currency_code', 'USD')->firstOrFail();
        $this->assertSame(4999, $usdProduct->defaultVariant->price_amount_minor);
    }

    public function test_inactive_or_unsupported_currency_rejected(): void
    {
        $vendor = $this->createVendorUser();
        Currency::query()->where('code', 'USD')->update(['is_active' => false]);

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'currency_code' => 'USD',
                'sku' => 'BAD-USD',
                'price' => '10.00',
            ]))
            ->assertSessionHasErrors('currency_code');

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'currency_code' => 'EUR',
                'sku' => 'BAD-EUR',
            ]))
            ->assertSessionHasErrors('currency_code');
    }

    public function test_money_and_quantity_validation_rules(): void
    {
        $vendor = $this->createVendorUser();

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'MONEY-1',
                'currency_code' => 'USD',
                'price' => '10.999',
            ]))
            ->assertSessionHasErrors('price');

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'MONEY-2',
                'currency_code' => 'SYP',
                'price' => '10.5',
            ]))
            ->assertSessionHasErrors('price');

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'MONEY-3',
                'price' => '100',
                'compare_at_price' => '100',
            ]))
            ->assertSessionHasErrors('compare_at_price');

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'MONEY-4',
                'price' => '0',
            ]))
            ->assertSessionHasErrors('price');

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'MONEY-5',
                'price' => '-5',
            ]))
            ->assertSessionHasErrors('price');

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'MONEY-6',
                'quantity' => -1,
            ]))
            ->assertSessionHasErrors('quantity');

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'MONEY-OK',
                'price' => '100',
                'compare_at_price' => '150',
                'quantity' => 0,
            ]))
            ->assertRedirect();

        $product = Product::query()->latest('id')->firstOrFail();
        $this->assertSame(100, $product->defaultVariant->price_amount_minor);
        $this->assertSame(150, $product->defaultVariant->compare_at_amount_minor);
        $this->assertSame(0, $product->defaultVariant->quantity);
    }

    public function test_category_and_brand_assignment_rules(): void
    {
        $vendor = $this->createVendorUser();
        $root = Category::factory()->create();
        $child = Category::factory()->childOf($root)->create();
        $leaf = Category::factory()->childOf($child)->create();
        $inactiveLeaf = Category::factory()->childOf($child)->inactive()->create();
        $inactiveRoot = Category::factory()->inactive()->create();
        $orphanUnderInactive = Category::factory()->childOf($inactiveRoot)->create();
        $activeBrand = Brand::factory()->create();
        $inactiveBrand = Brand::factory()->inactive()->create();

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'CAT-NULL',
                'category_id' => null,
            ]))
            ->assertRedirect();

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'CAT-LEAF',
                'category_id' => $leaf->id,
                'brand_id' => $activeBrand->id,
                'translations' => [
                    'en' => ['name' => 'Leaf Product'],
                    'ar' => ['name' => ''],
                ],
            ]))
            ->assertRedirect();

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'CAT-ROOT',
                'category_id' => $root->id,
            ]))
            ->assertSessionHasErrors('category_id');

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'CAT-MID',
                'category_id' => $child->id,
            ]))
            ->assertSessionHasErrors('category_id');

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'CAT-INACTIVE',
                'category_id' => $inactiveLeaf->id,
            ]))
            ->assertSessionHasErrors('category_id');

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'CAT-ANCESTOR',
                'category_id' => $orphanUnderInactive->id,
            ]))
            ->assertSessionHasErrors('category_id');

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'BRAND-BAD',
                'brand_id' => $inactiveBrand->id,
            ]))
            ->assertSessionHasErrors('brand_id');
    }

    public function test_partial_translations_and_stable_unique_slug(): void
    {
        $vendor = $this->createVendorUser();

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'TR-AR',
                'translations' => [
                    'ar' => ['name' => 'منتج عربي فقط'],
                    'en' => ['name' => ''],
                ],
            ]))
            ->assertRedirect();

        $arabicOnly = Product::query()->latest('id')->firstOrFail();
        $this->assertSame(1, $arabicOnly->translations()->count());
        $this->assertSame('منتج عربي فقط', $arabicOnly->name('ar'));
        $this->assertSame('منتج عربي فقط', $arabicOnly->name('en'));
        $this->assertTrue($arabicOnly->hasMissingTranslation('en'));
        $this->assertTrue(str_starts_with($arabicOnly->slug, 'product-'));

        $originalSlug = $arabicOnly->slug;

        $this->actingAs($vendor)
            ->put(route('vendor.products.update', $arabicOnly), $this->productPayload([
                'slug' => $originalSlug,
                'sku' => 'TR-AR',
                'translations' => [
                    'ar' => ['name' => 'اسم جديد'],
                    'en' => ['name' => ''],
                ],
            ]))
            ->assertRedirect();

        $this->assertSame($originalSlug, $arabicOnly->fresh()->slug);

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'TR-EN',
                'slug' => 'custom-slug',
                'translations' => [
                    'en' => ['name' => 'English Only'],
                    'ar' => ['name' => ''],
                ],
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('products', ['slug' => 'custom-slug']);

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'TR-DUP',
                'slug' => 'custom-slug',
            ]))
            ->assertSessionHasErrors('slug');
    }

    public function test_variable_product_without_matrix_is_rejected_and_simple_still_works(): void
    {
        $vendor = $this->createVendorUser();

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'type' => 'variable',
                'sku' => 'VAR-1',
            ]))
            ->assertSessionHasErrors('attributes');

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'type' => 'simple',
                'sku' => 'SIMPLE-STILL',
            ]))
            ->assertRedirect();
    }

    public function test_archive_works_and_no_hard_delete_route_exists(): void
    {
        $vendor = $this->createVendorUser();

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload(['sku' => 'ARCH-1']))
            ->assertRedirect();

        $product = Product::query()->latest('id')->firstOrFail();

        $this->actingAs($vendor)
            ->post(route('vendor.products.archive', $product))
            ->assertRedirect(route('vendor.products'));

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertSame(ProductStatus::Archived, Product::withTrashed()->findOrFail($product->id)->status);
        $this->assertSoftDeleted('product_variants', ['product_id' => $product->id]);

        $this->assertFalse(Route::has('vendor.products.destroy'));
        $this->assertFalse(Route::has('vendor.products.delete'));
    }

    public function test_public_catalog_routes_use_persisted_visibility(): void
    {
        $this->get('/p/linen-throw')->assertNotFound();
        $this->get(route('storefront.product', 'linen-throw'))->assertNotFound();
        $this->get('/p/missing-item')->assertNotFound();
        $this->get(route('home'))->assertOk();
        $this->get(route('storefront.search'))->assertOk();
    }

    public function test_vendor_products_index_empty_state_and_list(): void
    {
        $vendor = $this->createVendorUser();

        $this->actingAs($vendor)
            ->get(route('vendor.products'))
            ->assertOk()
            ->assertSee(__('No products yet'));

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'LIST-1',
                'translations' => [
                    'en' => ['name' => 'Listed Item'],
                    'ar' => ['name' => ''],
                ],
            ]))
            ->assertRedirect();

        $this->actingAs($vendor)
            ->get(route('vendor.products'))
            ->assertOk()
            ->assertSee('Listed Item')
            ->assertSee('LIST-1')
            ->assertSee(__('Draft'));
    }

    public function test_quantity_above_unsigned_int_max_is_rejected_by_http_and_service(): void
    {
        $vendor = $this->createVendorUser();
        $overflow = (string) (ProductVariant::MAX_QUANTITY + 1);

        $this->actingAs($vendor)
            ->post(route('vendor.products.store'), $this->productPayload([
                'sku' => 'QTY-OVERFLOW',
                'quantity' => $overflow,
            ]))
            ->assertSessionHasErrors('quantity');

        $service = app(ProductService::class);

        try {
            $service->createSimpleDraft($vendor->vendor->store, $this->productPayload([
                'sku' => 'QTY-SVC',
                'quantity' => $overflow,
            ]));
            $this->fail('Expected ValidationException for oversized quantity.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }

        $product = $service->createSimpleDraft($vendor->vendor->store, $this->productPayload([
            'sku' => 'QTY-MAX',
            'quantity' => ProductVariant::MAX_QUANTITY,
        ]));

        $this->assertSame(ProductVariant::MAX_QUANTITY, $product->defaultVariant->quantity);
    }

    public function test_update_rechecks_status_after_row_lock(): void
    {
        $vendor = $this->createVendorUser();
        $product = Product::factory()->create([
            'store_id' => $vendor->vendor->store->id,
            'status' => ProductStatus::Draft,
        ]);

        DB::table('products')->where('id', $product->id)->update([
            'status' => ProductStatus::Suspended->value,
        ]);

        $this->assertSame(ProductStatus::Draft, $product->status);

        $service = app(ProductService::class);

        try {
            $service->updateSimpleDraft($product, $this->productPayload([
                'sku' => $product->defaultVariant->sku,
                'slug' => $product->slug,
                'currency_code' => $product->currency_code,
            ]));
            $this->fail('Expected ValidationException after locked status recheck.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
    }

    public function test_archive_rechecks_suspended_status_after_row_lock(): void
    {
        $vendor = $this->createVendorUser();
        $product = Product::factory()->create([
            'store_id' => $vendor->vendor->store->id,
            'status' => ProductStatus::Draft,
        ]);

        DB::table('products')->where('id', $product->id)->update([
            'status' => ProductStatus::Suspended->value,
        ]);

        $this->assertSame(ProductStatus::Draft, $product->status);

        try {
            app(ProductService::class)->archive($product);
            $this->fail('Expected ValidationException after locked archive recheck.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'status' => ProductStatus::Suspended->value,
            'deleted_at' => null,
        ]);
    }
}
