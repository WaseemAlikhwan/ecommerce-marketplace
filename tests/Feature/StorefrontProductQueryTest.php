<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ProductImageService;
use App\Services\ProductPublicationService;
use App\Services\ProductService;
use App\Services\Storefront\StorefrontProductQuery;
use App\Storefront\CatalogCriteriaIssueCode;
use App\Storefront\Presentation\ProductCardPresenter;
use App\Storefront\Presentation\ProductDetailPresenter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class StorefrontProductQueryTest extends TestCase
{
    use RefreshDatabase;

    private StorefrontProductQuery $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = app(StorefrontProductQuery::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{vendor: User, product: Product, category: Category, brand: Brand}
     */
    private function publishSimple(array $overrides = []): array
    {
        Storage::fake('public');
        $vendor = $overrides['vendor'] ?? $this->createVendorUser();
        $this->actingAs($vendor);

        $category = $overrides['category'] ?? Category::factory()->create(['is_active' => true]);
        $brand = $overrides['brand'] ?? Brand::factory()->create(['is_active' => true]);

        $product = app(ProductService::class)->createSimpleDraft($vendor->vendor->store, [
            'type' => 'simple',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'currency_code' => $overrides['currency_code'] ?? 'SYP',
            'sku' => $overrides['sku'] ?? ('S8A-'.uniqid()),
            'price' => $overrides['price'] ?? '100',
            'compare_at_price' => $overrides['compare_at_price'] ?? null,
            'quantity' => $overrides['quantity'] ?? 5,
            'translations' => [
                'ar' => [
                    'name' => $overrides['name_ar'] ?? 'منتج تجريبي',
                    'short_description' => $overrides['short_ar'] ?? 'وصف قصير',
                ],
                'en' => [
                    'name' => $overrides['name_en'] ?? 'Sample Product',
                    'short_description' => $overrides['short_en'] ?? 'Short blurb',
                ],
            ],
        ]);
        app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());
        app(ProductPublicationService::class)->publish($product->fresh());

        return [
            'vendor' => $vendor,
            'product' => $product->fresh(['translations', 'variants', 'store', 'currency']),
            'category' => $category,
            'brand' => $brand,
        ];
    }

    public function test_keyword_search_ar_en_and_literal_wildcards(): void
    {
        $this->publishSimple([
            'name_en' => 'Alpha Phone',
            'name_ar' => "\u{0647}\u{0627}\u{062A}\u{0641} \u{0623}\u{0644}\u{0641}\u{0627}",
            'sku' => 'KW-1',
        ]);
        $this->publishSimple([
            'name_en' => 'Beta Case 100% cotton_soft',
            'name_ar' => "\u{063A}\u{0637}\u{0627}\u{0621}",
            'sku' => 'KW-2',
        ]);

        $this->assertCount(1, $this->query->get($this->query->resolveCriteria(['q' => "\u{0647}\u{0627}\u{062A}\u{0641}"])));
        $this->assertCount(1, $this->query->get($this->query->resolveCriteria(['q' => 'Phone'])));
        $this->assertCount(1, $this->query->get($this->query->resolveCriteria(['q' => '100%'])));
        $this->assertCount(1, $this->query->get($this->query->resolveCriteria(['q' => 'cotton_soft'])));
        $this->assertCount(0, $this->query->get($this->query->resolveCriteria(['q' => 'cotton%soft'])));
    }

    public function test_price_filter_currency_rules(): void
    {
        $this->publishSimple(['price' => '10.50', 'currency_code' => 'USD', 'sku' => 'USD-1']);
        $this->publishSimple(['price' => '20.00', 'currency_code' => 'USD', 'sku' => 'USD-2']);
        $this->publishSimple(['price' => '1000', 'currency_code' => 'SYP', 'sku' => 'SYP-1']);

        $missingCurrency = $this->query->resolveCriteria([
            'min_price' => '10',
            'max_price' => '30',
        ]);
        $this->assertContains(CatalogCriteriaIssueCode::PRICE_CURRENCY_REQUIRED, $missingCurrency->issues);
        $this->assertNull($missingCurrency->minPriceMinor);

        $usd = $this->query->resolveCriteria([
            'currency' => 'USD',
            'min_price' => '10.50',
            'max_price' => '10.50',
        ]);
        $this->assertSame(1050, $usd->minPriceMinor);
        $this->assertCount(1, $this->query->get($usd));

        $badRange = $this->query->resolveCriteria([
            'currency' => 'USD',
            'min_price' => '30',
            'max_price' => '10',
        ]);
        $this->assertContains(CatalogCriteriaIssueCode::PRICE_MIN_GT_MAX, $badRange->issues);
        $this->assertNull($badRange->minPriceMinor);
        $this->assertNull($badRange->maxPriceMinor);
    }

    public function test_availability_and_zero_stock_pricing_aggregates(): void
    {
        ['product' => $inStock] = $this->publishSimple(['quantity' => 3, 'price' => '50', 'sku' => 'STK-1']);
        ['product' => $out] = $this->publishSimple(['quantity' => 0, 'price' => '10', 'sku' => 'STK-0']);

        $ids = $this->query->get($this->query->resolveCriteria(['availability' => 'in_stock']))->pluck('id');
        $this->assertTrue($ids->contains($inStock->id));
        $this->assertFalse($ids->contains($out->id));

        $card = $this->query->get($this->query->resolveCriteria([]))->firstWhere('id', $out->id);
        $this->assertSame(10, (int) $card->getAttribute(StorefrontProductQuery::AGG_MIN_PRICE));
        $this->assertSame(0, (int) $card->getAttribute(StorefrontProductQuery::AGG_IN_STOCK));
    }

    public function test_soft_deleted_variant_excluded_from_aggregates(): void
    {
        ['product' => $product] = $this->publishSimple(['price' => '100', 'quantity' => 2, 'sku' => 'DEL-1']);
        $live = $product->variants()->first();

        $extra = ProductVariant::query()->create([
            'product_id' => $product->id,
            'store_id' => $product->store_id,
            'sku' => 'DEL-EXTRA-'.uniqid(),
            'combination_key' => 'extra-'.uniqid(),
            'price_amount_minor' => 1,
            'compare_at_amount_minor' => null,
            'quantity' => 9,
        ]);
        $extra->delete();

        $card = $this->query->get($this->query->resolveCriteria([]))->firstWhere('id', $product->id);
        $this->assertSame((int) $live->price_amount_minor, (int) $card->getAttribute(StorefrontProductQuery::AGG_MIN_PRICE));
        $this->assertSame((int) $live->price_amount_minor, (int) $card->getAttribute(StorefrontProductQuery::AGG_MAX_PRICE));
    }

    public function test_category_brand_store_filters(): void
    {
        $root = Category::factory()->create(['is_active' => true, 'parent_id' => null, 'slug' => 'root-nav']);
        $leaf = Category::factory()->create(['is_active' => true, 'parent_id' => $root->id, 'slug' => 'leaf-nav']);
        $other = Category::factory()->create(['is_active' => true, 'parent_id' => null, 'slug' => 'other-nav']);
        $brandA = Brand::factory()->create(['is_active' => true, 'slug' => 'brand-a']);
        $brandB = Brand::factory()->create(['is_active' => true, 'slug' => 'brand-b']);

        ['product' => $p1, 'vendor' => $v1] = $this->publishSimple([
            'category' => $leaf,
            'brand' => $brandA,
            'sku' => 'CAT-1',
        ]);
        $this->publishSimple([
            'category' => $other,
            'brand' => $brandB,
            'sku' => 'CAT-2',
        ]);

        $this->assertSame(
            [$p1->id],
            $this->query->get($this->query->resolveCriteria(['category' => $root->slug]))->pluck('id')->all(),
        );
        $this->assertSame(
            [$p1->id],
            $this->query->get($this->query->resolveCriteria(['brand' => 'brand-a']))->pluck('id')->all(),
        );
        $this->assertSame(
            [$p1->id],
            $this->query->get($this->query->resolveCriteria(['store' => $v1->vendor->store->slug]))->pluck('id')->all(),
        );
    }

    public function test_attribute_same_variant_combination(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);
        $category = Category::factory()->create(['is_active' => true]);

        $color = Attribute::factory()->create(['code' => 'color', 'is_active' => true]);
        $size = Attribute::factory()->create(['code' => 'size', 'is_active' => true]);
        $red = AttributeValue::factory()->for($color)->create(['code' => 'red', 'is_active' => true]);
        $blue = AttributeValue::factory()->for($color)->create(['code' => 'blue', 'is_active' => true]);
        $small = AttributeValue::factory()->for($size)->create(['code' => 's', 'is_active' => true]);
        $medium = AttributeValue::factory()->for($size)->create(['code' => 'm', 'is_active' => true]);

        $attrs = [
            'color' => $color,
            'size' => $size,
            'red' => $red,
            'blue' => $blue,
            'small' => $small,
            'medium' => $medium,
        ];

        $match = app(ProductService::class)->createVariableDraft($vendor->vendor->store, [
            'type' => 'variable',
            'category_id' => $category->id,
            'currency_code' => 'SYP',
            'translations' => [
                'ar' => ['name' => 'Ù…ØªØºÙŠØ± Ù…Ø·Ø§Ø¨Ù‚'],
                'en' => ['name' => 'Match Variable'],
            ],
            'attributes' => [
                ['attribute_id' => $color->id, 'value_ids' => [$red->id, $blue->id]],
                ['attribute_id' => $size->id, 'value_ids' => [$small->id, $medium->id]],
            ],
            'variants' => [
                [
                    'value_ids' => [$red->id, $small->id],
                    'sku' => 'ATTR-RS',
                    'price' => '100',
                    'quantity' => 2,
                    'is_default' => true,
                ],
                [
                    'value_ids' => [$blue->id, $medium->id],
                    'sku' => 'ATTR-BM',
                    'price' => '120',
                    'quantity' => 2,
                    'is_default' => false,
                ],
            ],
        ]);
        app(ProductImageService::class)->upload($match, $this->makeProductImageUpload());
        app(ProductPublicationService::class)->publish($match->fresh());

        $falsePositive = app(ProductService::class)->createVariableDraft($vendor->vendor->store, [
            'type' => 'variable',
            'category_id' => $category->id,
            'currency_code' => 'SYP',
            'translations' => [
                'ar' => ['name' => 'Ù…ØªØºÙŠØ± Ø®Ø§Ø·Ø¦'],
                'en' => ['name' => 'False Positive'],
            ],
            'attributes' => [
                ['attribute_id' => $color->id, 'value_ids' => [$red->id, $blue->id]],
                ['attribute_id' => $size->id, 'value_ids' => [$small->id, $medium->id]],
            ],
            'variants' => [
                [
                    'value_ids' => [$red->id, $medium->id],
                    'sku' => 'ATTR-RM',
                    'price' => '100',
                    'quantity' => 2,
                    'is_default' => true,
                ],
                [
                    'value_ids' => [$blue->id, $small->id],
                    'sku' => 'ATTR-BS',
                    'price' => '120',
                    'quantity' => 2,
                    'is_default' => false,
                ],
            ],
        ]);
        app(ProductImageService::class)->upload($falsePositive, $this->makeProductImageUpload());
        app(ProductPublicationService::class)->publish($falsePositive->fresh());

        $ids = $this->query->get($this->query->resolveCriteria([
            'attrs' => [
                'color' => ['red'],
                'size' => ['s'],
            ],
        ]))->pluck('id');

        $this->assertTrue($ids->contains($match->id));
        $this->assertFalse($ids->contains($falsePositive->id));
        unset($attrs);
    }

    public function test_sorts_are_deterministic(): void
    {
        ['product' => $a] = $this->publishSimple(['name_en' => 'B Name', 'price' => '30', 'sku' => 'SORT-A']);
        usleep(1000);
        ['product' => $b] = $this->publishSimple(['name_en' => 'A Name', 'price' => '30', 'sku' => 'SORT-B']);
        usleep(1000);
        ['product' => $c] = $this->publishSimple(['name_en' => 'C Name', 'price' => '10', 'sku' => 'SORT-C']);

        $newest = $this->query->get($this->query->resolveCriteria(['sort' => 'newest']))->pluck('id')->all();
        $this->assertSame([$c->id, $b->id, $a->id], $newest);

        $name = $this->query->get($this->query->resolveCriteria(['sort' => 'name']), 'en')->pluck('id')->all();
        $this->assertSame([$b->id, $a->id, $c->id], $name);

        $priceAsc = $this->query->get($this->query->resolveCriteria([
            'currency' => 'SYP',
            'sort' => 'price_asc',
        ]))->pluck('id')->all();
        $this->assertSame($c->id, $priceAsc[0]);
    }

    public function test_detail_related_and_hidden_lookup(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        ['product' => $main] = $this->publishSimple(['category' => $category, 'sku' => 'REL-MAIN']);
        for ($i = 0; $i < 5; $i++) {
            $this->publishSimple(['category' => $category, 'sku' => 'REL-'.$i]);
        }

        $detail = $this->query->findVisibleBySlugOrFail($main->slug);
        $this->assertCount(4, $detail->relatedStorefrontProducts);
        $this->assertFalse($detail->relatedStorefrontProducts->contains(fn (Product $p): bool => $p->id === $main->id));

        $main->delete();
        $this->expectException(ModelNotFoundException::class);
        $this->query->findVisibleBySlugOrFail($main->slug);
    }

    public function test_presenters_zero_query_and_money_strings(): void
    {
        ['product' => $product] = $this->publishSimple([
            'price' => '12.50',
            'currency_code' => 'USD',
            'compare_at_price' => '20.00',
            'sku' => 'PRES-1',
        ]);

        $cardModel = $this->query->get($this->query->resolveCriteria([]))->firstWhere('id', $product->id);
        $detail = $this->query->findVisibleBySlugOrFail($product->slug);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $card = app(ProductCardPresenter::class)->present($cardModel, 'en');
        $detailState = app(ProductDetailPresenter::class)->present($detail, 'en');
        $this->assertSame([], DB::getQueryLog());

        $this->assertIsString($card->minPrice['amount_minor']);
        $this->assertIsString($detailState->selectedPrice['amount_minor']);
        $this->assertSame(ProductType::Simple->value, $card->type);
        $this->assertNotNull($card->compareAtPrice);
        $this->assertSame(400, $card->imageWidth);
        $this->assertSame(400, $card->imageHeight);
        $this->assertSame(400, $detailState->gallery[0]['width']);
        $this->assertSame(400, $detailState->gallery[0]['height']);
    }

    public function test_card_presenter_requires_in_stock_aggregate_key(): void
    {
        ['product' => $product] = $this->publishSimple(['sku' => 'PRES-STOCK']);
        $cardModel = $this->query->get($this->query->resolveCriteria([]))->firstWhere('id', $product->id);
        $attributes = $cardModel->getAttributes();
        unset($attributes[StorefrontProductQuery::AGG_IN_STOCK]);
        $cardModel->setRawAttributes($attributes, true);

        $this->expectException(RuntimeException::class);
        app(ProductCardPresenter::class)->present($cardModel, 'en');
    }

    public function test_detail_presenter_requires_declared_category_ancestors(): void
    {
        $root = Category::factory()->create(['is_active' => true, 'parent_id' => null]);
        $leaf = Category::factory()->create(['is_active' => true, 'parent_id' => $root->id]);
        ['product' => $product] = $this->publishSimple([
            'category' => $leaf,
            'sku' => 'PRES-ANCESTOR',
        ]);

        $detail = $this->query->findVisibleBySlugOrFail($product->slug);
        $detail->category->unsetRelation('parent');

        $this->expectException(LogicException::class);
        app(ProductDetailPresenter::class)->present($detail, 'en');
    }

    public function test_detail_presenter_requires_related_in_stock_aggregate_key(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        ['product' => $main] = $this->publishSimple([
            'category' => $category,
            'sku' => 'PRES-RELATED-MAIN',
        ]);
        $this->publishSimple([
            'category' => $category,
            'sku' => 'PRES-RELATED-OTHER',
        ]);

        $detail = $this->query->findVisibleBySlugOrFail($main->slug);
        $related = $detail->relatedStorefrontProducts->firstOrFail();
        $attributes = $related->getAttributes();
        unset($attributes[StorefrontProductQuery::AGG_IN_STOCK]);
        $related->setRawAttributes($attributes, true);

        $this->expectException(LogicException::class);
        app(ProductDetailPresenter::class)->present($detail, 'en');
    }

    public function test_query_count_stable_as_result_grows(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->publishSimple(['sku' => 'N1-'.$i, 'name_en' => 'Batch One '.$i]);
        }
        $resolved = $this->query->resolveCriteria([]);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->query->get($resolved);
        $countSmall = count(DB::getQueryLog());

        for ($i = 0; $i < 10; $i++) {
            $this->publishSimple(['sku' => 'N2-'.$i, 'name_en' => 'Batch Two '.$i]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->query->get($resolved);
        $countLarge = count(DB::getQueryLog());

        $this->assertSame($countSmall, $countLarge);
    }

    public function test_malformed_and_unresolved_filters_fail_closed_without_broadening(): void
    {
        ['product' => $visible] = $this->publishSimple(['sku' => 'SAFE-1', 'name_en' => 'Safe Visible']);

        $longName = 'SafeVisibleToken'.str_repeat('x', 70);
        $this->publishSimple(['sku' => 'SAFE-LONG', 'name_en' => $longName]);

        $truncated = $this->query->resolveCriteria([
            'q' => $longName.'YYYY',
        ]);
        $this->assertContains(CatalogCriteriaIssueCode::Q_TRUNCATED, $truncated->allIssues());
        $this->assertFalse($truncated->hasUnresolvedFilters());
        $this->assertSame(80, mb_strlen((string) $truncated->criteria->q));
        $this->assertGreaterThanOrEqual(1, $this->query->get($truncated)->count());

        $malformedQ = $this->query->resolveCriteria(['q' => ['array']]);
        $this->assertContains(CatalogCriteriaIssueCode::Q_MALFORMED, $malformedQ->allIssues());
        $this->assertFalse($malformedQ->hasUnresolvedFilters());
        $this->assertTrue($this->query->get($malformedQ)->contains(fn (Product $p): bool => $p->id === $visible->id));

        $missingCategory = $this->query->resolveCriteria(['category' => 'no-such-category']);
        $this->assertContains(CatalogCriteriaIssueCode::CATEGORY_UNRESOLVED, $missingCategory->allIssues());
        $this->assertTrue($missingCategory->hasUnresolvedFilters());
        $this->assertCount(0, $this->query->get($missingCategory));

        $missingBrand = $this->query->resolveCriteria(['brand' => 'no-such-brand']);
        $this->assertContains(CatalogCriteriaIssueCode::BRAND_UNRESOLVED, $missingBrand->allIssues());
        $this->assertCount(0, $this->query->get($missingBrand));

        $missingStore = $this->query->resolveCriteria(['store' => 'no-such-store']);
        $this->assertContains(CatalogCriteriaIssueCode::STORE_UNRESOLVED, $missingStore->allIssues());
        $this->assertCount(0, $this->query->get($missingStore));

        $missingCurrency = $this->query->resolveCriteria(['currency' => 'ZZZ']);
        $this->assertContains(CatalogCriteriaIssueCode::CURRENCY_UNRESOLVED, $missingCurrency->allIssues());
        $this->assertCount(0, $this->query->get($missingCurrency));

        $unknownAttr = $this->query->resolveCriteria(['attrs' => ['nope' => ['x']]]);
        $this->assertContains(CatalogCriteriaIssueCode::ATTRIBUTE_INACTIVE_OR_UNKNOWN, $unknownAttr->allIssues());
        $this->assertTrue($unknownAttr->hasUnresolvedFilters());
        $this->assertCount(0, $this->query->get($unknownAttr));
    }

    public function test_search_literals_backslash_and_locale_only_matches(): void
    {
        $this->publishSimple([
            'name_en' => 'Path\\Literal',
            'name_ar' => 'مسار',
            'sku' => 'BS-1',
        ]);
        $this->publishSimple([
            'name_en' => 'Other',
            'name_ar' => 'هاتف فقط',
            'sku' => 'AR-ONLY',
        ]);

        $this->assertCount(1, $this->query->get($this->query->resolveCriteria(['q' => 'Path\\Literal'])));
        $this->assertCount(1, $this->query->get($this->query->resolveCriteria(['q' => 'هاتف'])));
        $this->assertCount(0, $this->query->get($this->query->resolveCriteria(['q' => 'Path%Literal'])));
        $this->assertCount(0, $this->query->get($this->query->resolveCriteria(['q' => 'Path_Literal'])));

        $sql = $this->query->browse($this->query->resolveCriteria(['q' => 'phone']))->toSql();
        $this->assertStringNotContainsString("'%phone%'", $sql);
        $this->assertStringContainsString('ESCAPE', strtoupper($sql));
    }

    public function test_inactive_attribute_value_and_mixed_selection_fail_closed(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);
        $category = Category::factory()->create(['is_active' => true]);

        $color = Attribute::factory()->create(['code' => 'color', 'is_active' => true]);
        $red = AttributeValue::factory()->for($color)->create(['code' => 'red', 'is_active' => true]);
        $blue = AttributeValue::factory()->for($color)->create(['code' => 'blue', 'is_active' => false]);

        $product = app(ProductService::class)->createVariableDraft($vendor->vendor->store, [
            'type' => 'variable',
            'category_id' => $category->id,
            'currency_code' => 'SYP',
            'translations' => [
                'ar' => ['name' => 'لون'],
                'en' => ['name' => 'Color Product'],
            ],
            'attributes' => [
                ['attribute_id' => $color->id, 'value_ids' => [$red->id]],
            ],
            'variants' => [
                [
                    'value_ids' => [$red->id],
                    'sku' => 'CLR-RED',
                    'price' => '100',
                    'quantity' => 2,
                    'is_default' => true,
                ],
            ],
        ]);
        app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());
        app(ProductPublicationService::class)->publish($product->fresh());

        $inactiveValue = $this->query->resolveCriteria(['attrs' => ['color' => ['blue']]]);
        $this->assertContains(CatalogCriteriaIssueCode::ATTRIBUTE_VALUE_INACTIVE_OR_UNKNOWN, $inactiveValue->allIssues());
        $this->assertCount(0, $this->query->get($inactiveValue));

        $mixed = $this->query->resolveCriteria(['attrs' => ['color' => ['red', 'blue']]]);
        $this->assertContains(CatalogCriteriaIssueCode::ATTRIBUTE_VALUE_INACTIVE_OR_UNKNOWN, $mixed->allIssues());
        $this->assertTrue($mixed->hasUnresolvedFilters());
        $this->assertCount(0, $this->query->get($mixed));

        $ok = $this->query->resolveCriteria(['attrs' => ['color' => ['red']]]);
        $this->assertFalse($ok->hasUnresolvedFilters());
        $this->assertTrue($this->query->get($ok)->contains(fn (Product $p): bool => $p->id === $product->id));
    }

    public function test_detail_fails_closed_when_default_variant_soft_deleted(): void
    {
        ['product' => $product] = $this->publishSimple(['sku' => 'CORRUPT-1', 'quantity' => 3]);
        $default = $product->variants()->firstOrFail();
        $default->delete();

        $this->expectException(ModelNotFoundException::class);
        $this->query->findVisibleBySlugOrFail($product->slug);
    }

    public function test_detail_fails_closed_when_all_variants_removed(): void
    {
        ['product' => $product] = $this->publishSimple(['sku' => 'CORRUPT-2', 'quantity' => 3]);
        ProductVariant::query()->where('product_id', $product->id)->delete();

        $this->expectException(ModelNotFoundException::class);
        $this->query->findVisibleBySlugOrFail($product->slug);
    }

    public function test_public_variant_payload_omits_sku_and_quantity(): void
    {
        ['product' => $product] = $this->publishSimple([
            'sku' => 'PUB-VAR-1',
            'price' => '15.00',
            'currency_code' => 'USD',
            'quantity' => 7,
        ]);

        $detail = $this->query->findVisibleBySlugOrFail($product->slug);
        $state = app(ProductDetailPresenter::class)->present($detail, 'en');
        $variant = $state->variants[0];

        $this->assertArrayHasKey('id', $variant);
        $this->assertArrayHasKey('in_stock', $variant);
        $this->assertArrayHasKey('price', $variant);
        $this->assertArrayHasKey('selection', $variant);
        $this->assertArrayNotHasKey('sku', $variant);
        $this->assertArrayNotHasKey('quantity', $variant);
        $this->assertTrue($variant['in_stock']);
        $this->assertIsString($variant['price']['amount_minor']);
    }

    public function test_variable_presenter_is_query_free_and_rejects_missing_nested_relations(): void
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
                'ar' => ['name' => 'متغير'],
                'en' => ['name' => 'Variable Hue'],
            ],
            'attributes' => [
                ['attribute_id' => $color->id, 'value_ids' => [$red->id]],
            ],
            'variants' => [
                [
                    'value_ids' => [$red->id],
                    'sku' => 'HUE-RED',
                    'price' => '90',
                    'quantity' => 1,
                    'is_default' => true,
                ],
            ],
        ]);
        app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());
        app(ProductPublicationService::class)->publish($product->fresh());

        $detail = $this->query->findVisibleBySlugOrFail($product->fresh()->slug);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $state = app(ProductDetailPresenter::class)->present($detail, 'en');
        $this->assertSame([], DB::getQueryLog());
        $this->assertNotSame([], $state->variants);
        $this->assertArrayNotHasKey('sku', $state->variants[0]);

        $broken = $detail->withoutRelations();
        $broken->setRelation('translations', $detail->translations);
        $broken->setRelation('store', $detail->store);
        $broken->setRelation('currency', $detail->currency);
        $broken->setRelation('images', $detail->images);
        $broken->setRelation('variants', $detail->variants);
        $broken->setRelation('relatedStorefrontProducts', $detail->relatedStorefrontProducts);
        $broken->setRelation('category', $detail->category);
        $broken->setRelation('productAttributes', $detail->productAttributes);
        $broken->variants->each(function (ProductVariant $variant): void {
            $variant->unsetRelation('attributeValueLinks');
        });

        $this->expectException(LogicException::class);
        app(ProductDetailPresenter::class)->present($broken, 'en');
    }

    public function test_criteria_result_to_array_money_strings_from_query_layer(): void
    {
        $this->publishSimple(['price' => '12.50', 'currency_code' => 'USD', 'sku' => 'JSON-1']);

        $resolved = $this->query->resolveCriteria([
            'currency' => 'USD',
            'min_price' => '12.50',
            'max_price' => '12.50',
        ]);
        $payload = $resolved->toArray();

        $this->assertSame('1250', $payload['criteria']['min_price_minor']);
        $this->assertSame('1250', $payload['criteria']['max_price_minor']);
        $this->assertIsString($payload['criteria']['min_price_minor']);
        $this->assertIsString($payload['criteria']['max_price_minor']);

        $this->expectException(LogicException::class);
        $resolved->toArray($this->query->get($resolved));
    }
}
