<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Enums\VendorStatus;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductImageService;
use App\Services\ProductPublicationService;
use App\Services\ProductReadinessService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductStorefrontVisibilityScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{vendor: User, product: Product, category: Category, brand: Brand}
     */
    private function makeVisiblePublished(): array
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
            'sku' => 'VIS-'.uniqid(),
            'price' => '100',
            'quantity' => 0,
            'translations' => [
                'ar' => ['name' => 'منتج ظاهر'],
                'en' => ['name' => 'Visible Product'],
            ],
        ]);
        app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());
        app(ProductPublicationService::class)->publish($product->fresh());

        return [
            'vendor' => $vendor,
            'product' => $product->fresh(),
            'category' => $category,
            'brand' => $brand,
        ];
    }

    public function test_status_and_soft_delete_gates(): void
    {
        ['product' => $product] = $this->makeVisiblePublished();

        $this->assertTrue(Product::query()->storefrontVisible()->whereKey($product->id)->exists());
        $this->assertTrue(Product::query()->published()->whereKey($product->id)->exists());

        foreach ([ProductStatus::Draft, ProductStatus::Unpublished, ProductStatus::Suspended, ProductStatus::Archived] as $status) {
            $product->forceFill(['status' => $status])->save();
            $this->assertFalse(
                Product::query()->storefrontVisible()->whereKey($product->id)->exists(),
                "Status {$status->value} must be excluded",
            );
        }

        $product->forceFill(['status' => ProductStatus::Published])->save();
        $product->delete();

        $this->assertFalse(Product::query()->storefrontVisible()->whereKey($product->id)->exists());
        $this->assertFalse(Product::withTrashed()->storefrontVisible()->whereKey($product->id)->exists());
    }

    public function test_store_vendor_category_brand_currency_and_leaf_gates(): void
    {
        ['vendor' => $vendor, 'product' => $product, 'category' => $category, 'brand' => $brand] = $this->makeVisiblePublished();

        $vendor->vendor->store->forceFill(['status' => StoreStatus::Suspended])->save();
        $this->assertFalse(Product::query()->storefrontVisible()->whereKey($product->id)->exists());
        $vendor->vendor->store->forceFill(['status' => StoreStatus::Active])->save();

        $vendor->vendor->forceFill(['status' => VendorStatus::Suspended])->save();
        $this->assertFalse(Product::query()->storefrontVisible()->whereKey($product->id)->exists());
        $vendor->vendor->forceFill(['status' => VendorStatus::Approved])->save();

        $category->forceFill(['is_active' => false])->save();
        $this->assertFalse(Product::query()->storefrontVisible()->whereKey($product->id)->exists());
        $category->forceFill(['is_active' => true])->save();

        $parent = Category::factory()->create(['is_active' => false]);
        $leaf = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => true]);
        $product->forceFill(['category_id' => $leaf->id])->save();
        $this->assertFalse(Product::query()->storefrontVisible()->whereKey($product->id)->exists());

        $parent->forceFill(['is_active' => true])->save();
        $this->assertTrue(Product::query()->storefrontVisible()->whereKey($product->id)->exists());

        Category::factory()->create(['parent_id' => $leaf->id, 'is_active' => true]);
        $this->assertFalse(Product::query()->storefrontVisible()->whereKey($product->id)->exists());

        $product->forceFill(['category_id' => $category->id])->save();
        $brand->forceFill(['is_active' => false])->save();
        $this->assertFalse(Product::query()->storefrontVisible()->whereKey($product->id)->exists());

        $product->forceFill(['brand_id' => null])->save();
        $this->assertTrue(Product::query()->storefrontVisible()->whereKey($product->id)->exists());

        Currency::query()->where('code', 'SYP')->update(['is_active' => false]);
        $this->assertFalse(Product::query()->storefrontVisible()->whereKey($product->id)->exists());
        Currency::query()->where('code', 'SYP')->update(['is_active' => true]);
        $this->assertTrue(Product::query()->storefrontVisible()->whereKey($product->id)->exists());
    }

    public function test_zero_stock_and_inactive_historical_globals_remain_visible(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);
        $category = Category::factory()->create(['is_active' => true]);
        $color = Attribute::factory()->create(['is_active' => true]);
        $red = AttributeValue::factory()->for($color)->create(['is_active' => true]);

        $product = app(ProductService::class)->createVariableDraft($vendor->vendor->store, [
            'type' => 'variable',
            'category_id' => $category->id,
            'currency_code' => 'SYP',
            'translations' => [
                'ar' => ['name' => 'متغير'],
                'en' => ['name' => 'Variable'],
            ],
            'attributes' => [
                ['attribute_id' => $color->id, 'value_ids' => [$red->id]],
            ],
            'variants' => [[
                'value_ids' => [$red->id],
                'sku' => 'VIS-VAR-'.uniqid(),
                'price' => '50',
                'quantity' => 0,
                'is_default' => true,
            ]],
        ]);
        app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());
        app(ProductPublicationService::class)->publish($product->fresh());

        $color->forceFill(['is_active' => false])->save();
        $red->forceFill(['is_active' => false])->save();

        $this->assertTrue(Product::query()->storefrontVisible()->whereKey($product->id)->exists());
        $this->assertSame(0, $product->fresh()->defaultVariant->quantity);
    }

    public function test_scope_parity_with_visibility_issues_and_stable_query_count(): void
    {
        ['product' => $a] = $this->makeVisiblePublished();
        ['product' => $b, 'category' => $category] = $this->makeVisiblePublished();
        $category->forceFill(['is_active' => false])->save();

        $visibleIds = Product::query()->storefrontVisible()->orderBy('id')->pluck('id')->all();
        $this->assertContains($a->id, $visibleIds);
        $this->assertNotContains($b->id, $visibleIds);

        $readiness = app(ProductReadinessService::class);
        $this->assertSame(
            [],
            collect($readiness->evaluate($a->fresh())->visibilityIssues)->pluck('code')->all(),
        );
        $this->assertContains(
            'inactive_category',
            collect($readiness->evaluate($b->fresh())->visibilityIssues)->pluck('code')->all(),
        );

        DB::connection()->disableQueryLog();
        DB::flushQueryLog();
        DB::connection()->enableQueryLog();
        $countA = Product::query()->storefrontVisible()->count();
        $queriesA = count(DB::getQueryLog());
        DB::connection()->disableQueryLog();
        DB::flushQueryLog();

        ['product' => $c] = $this->makeVisiblePublished();
        ['product' => $d] = $this->makeVisiblePublished();

        DB::flushQueryLog();
        DB::connection()->enableQueryLog();
        $countB = Product::query()->storefrontVisible()->count();
        $queriesB = count(DB::getQueryLog());
        DB::connection()->disableQueryLog();

        $this->assertGreaterThanOrEqual($countA + 1, $countB);
        $this->assertSame($queriesA, $queriesB);
        $this->assertLessThanOrEqual(3, $queriesB);
        $this->assertTrue(Product::query()->storefrontVisible()->whereKey($c->id)->exists());
        $this->assertTrue(Product::query()->storefrontVisible()->whereKey($d->id)->exists());
    }

    public function test_fixed_depth_category_visibility_matrix(): void
    {
        ['vendor' => $vendor, 'product' => $product, 'brand' => $brand] = $this->makeVisiblePublished();

        $root = Category::factory()->create(['is_active' => true, 'parent_id' => null]);
        $product->forceFill(['category_id' => $root->id, 'brand_id' => $brand->id])->save();
        $this->assertTrue(Product::query()->storefrontVisible()->whereKey($product->id)->exists());

        $parent = Category::factory()->create(['is_active' => true, 'parent_id' => null]);
        $depth2 = Category::factory()->create(['is_active' => true, 'parent_id' => $parent->id]);
        $product->forceFill(['category_id' => $depth2->id])->save();
        $this->assertTrue(Product::query()->storefrontVisible()->whereKey($product->id)->exists());

        $grand = Category::factory()->create(['is_active' => true, 'parent_id' => null]);
        $mid = Category::factory()->create(['is_active' => true, 'parent_id' => $grand->id]);
        $depth3 = Category::factory()->create(['is_active' => true, 'parent_id' => $mid->id]);
        $product->forceFill(['category_id' => $depth3->id])->save();
        $this->assertTrue(Product::query()->storefrontVisible()->whereKey($product->id)->exists());

        $mid->forceFill(['is_active' => false])->save();
        $this->assertFalse(Product::query()->storefrontVisible()->whereKey($product->id)->exists());
        $mid->forceFill(['is_active' => true])->save();

        $grand->forceFill(['is_active' => false])->save();
        $this->assertFalse(Product::query()->storefrontVisible()->whereKey($product->id)->exists());
        $grand->forceFill(['is_active' => true])->save();

        Category::factory()->create(['is_active' => true, 'parent_id' => $depth3->id]);
        $this->assertFalse(Product::query()->storefrontVisible()->whereKey($product->id)->exists());

        // Malformed depth > 3: force a 4-level chain and assign the deepest leaf.
        $l1 = Category::factory()->create(['is_active' => true, 'parent_id' => null]);
        $l2 = Category::factory()->create(['is_active' => true, 'parent_id' => $l1->id]);
        $l3 = Category::factory()->create(['is_active' => true, 'parent_id' => $l2->id]);
        $l4 = Category::factory()->create(['is_active' => true, 'parent_id' => $l3->id]);
        $product->forceFill(['category_id' => $l4->id])->save();
        $this->assertGreaterThan(3, $l4->fresh(['parent.parent.parent'])->depth());
        $this->assertFalse(Product::query()->storefrontVisible()->whereKey($product->id)->exists());
    }
}
