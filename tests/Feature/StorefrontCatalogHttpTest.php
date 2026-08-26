<?php

namespace Tests\Feature;

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
use App\Services\Storefront\StorefrontBrowseService;
use App\Services\Storefront\StorefrontFilterOptionsService;
use App\Services\Storefront\StorefrontHomeService;
use App\Services\Storefront\StorefrontNavigationService;
use App\Support\Locale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorefrontCatalogHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{vendor: User, product: Product, category: Category}
     */
    private function publishSimple(array $overrides = []): array
    {
        $vendor = $overrides['vendor'] ?? $this->createVendorUser();
        $category = $overrides['category'] ?? Category::factory()->create([
            'is_active' => true,
        ]);
        $brand = $overrides['brand'] ?? Brand::factory()->create([
            'is_active' => true,
        ]);

        $this->actingAs($vendor);
        $product = app(ProductService::class)->createSimpleDraft($vendor->vendor->store, [
            'type' => 'simple',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'currency_code' => $overrides['currency_code'] ?? 'SYP',
            'sku' => $overrides['sku'] ?? ('HTTP-'.uniqid()),
            'price' => $overrides['price'] ?? '1250',
            'compare_at_price' => $overrides['compare_at_price'] ?? null,
            'quantity' => $overrides['quantity'] ?? 4,
            'translations' => [
                'ar' => [
                    'name' => $overrides['name_ar'] ?? 'منتج واجهة عربي',
                    'short_description' => 'وصف عربي',
                    'description' => 'تفاصيل المنتج بالعربية',
                ],
                'en' => [
                    'name' => $overrides['name_en'] ?? 'English Storefront Product',
                    'short_description' => 'English summary',
                    'description' => 'English product details',
                ],
            ],
        ]);
        app(ProductImageService::class)->upload(
            $product,
            $this->makeProductImageUpload(width: 640, height: 480),
        );
        app(ProductPublicationService::class)->publish($product->fresh());
        Auth::logout();

        return [
            'vendor' => $vendor,
            'product' => $product->fresh(),
            'category' => $category,
        ];
    }

    public function test_route_contract_and_empty_database_pages(): void
    {
        $this->assertSame('/', route('home', absolute: false));
        $this->assertSame('/search', route('storefront.search', absolute: false));
        $this->assertSame('/c/example', route('storefront.category', 'example', absolute: false));
        $this->assertSame('/s/example', route('storefront.store', 'example', absolute: false));
        $this->assertSame('/p/example', route('storefront.product', 'example', absolute: false));

        $home = $this->get(route('home'));
        $home->assertOk()->assertSee(__('No products available'));
        $this->get(route('storefront.search'))->assertOk()->assertSee(__('No matching products'));

        $category = Category::factory()->create(['is_active' => true]);
        $vendor = $this->createVendorUser();
        $this->get(route('storefront.category', $category->slug))->assertOk();
        $this->get(route('storefront.store', $vendor->vendor->store->slug))->assertOk();
        $this->get(route('storefront.product', 'missing-product'))->assertNotFound();
    }

    public function test_all_public_pages_render_visible_product_with_cart_and_without_wishlist_or_rating_ui(): void
    {
        ['vendor' => $vendor, 'product' => $product, 'category' => $category] = $this->publishSimple([
            'name_en' => 'Visible Linen',
            'name_ar' => 'كتان ظاهر',
        ]);

        $responses = [
            $this->withCookie(Locale::COOKIE, 'en')->get(route('home')),
            $this->withCookie(Locale::COOKIE, 'en')->get(route('storefront.search')),
            $this->withCookie(Locale::COOKIE, 'en')->get(route('storefront.category', $category->slug)),
            $this->withCookie(Locale::COOKIE, 'en')->get(route('storefront.store', $vendor->vendor->store->slug)),
            $this->withCookie(Locale::COOKIE, 'en')->get(route('storefront.product', $product->slug)),
        ];

        foreach ($responses as $index => $response) {
            $response
                ->assertOk()
                ->assertSee('Add to cart')
                ->assertDontSee('Wishlist');

            // Browse surfaces stay review/rating-free; PDP may show REV V1 section.
            if ($index < 4) {
                $response
                    ->assertDontSee('rating', false)
                    ->assertDontSee('review', false);
            }
        }

        $responses[1]->assertSee('Visible Linen');
        $responses[2]->assertDontSee('name="category"', false);
        $responses[3]->assertDontSee('name="store"', false);
        $responses[4]
            ->assertSee('width="640"', false)
            ->assertSee('height="480"', false)
            ->assertSee('loading="eager"', false)
            ->assertSee('fetchpriority="high"', false);
    }

    public function test_locales_and_malformed_queries_are_safe(): void
    {
        ['product' => $product] = $this->publishSimple([
            'name_en' => 'Bilingual Product',
            'name_ar' => 'منتج ثنائي اللغة',
        ]);

        $this->withCookie(Locale::COOKIE, 'en')
            ->get(route('storefront.product', $product->slug))
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('dir="ltr"', false)
            ->assertSee('Bilingual Product');

        $this->withCookie(Locale::COOKIE, 'ar')
            ->get(route('storefront.product', $product->slug))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('منتج ثنائي اللغة');

        $this->withCookie(Locale::COOKIE, 'en')
            ->get('/search?q%5B%5D=x&currency%5B%5D=USD&availability%5B%5D=in_stock&sort%5B%5D=name&attrs=bad&page%5B%5D=2')
            ->assertOk()
            ->assertSee('The search text format was not valid and was ignored.')
            ->assertSee('The attribute filters format was not valid and was ignored.');

        $this->withCookie(Locale::COOKIE, 'ar')
            ->get('/search?category=missing-category')
            ->assertOk()
            ->assertSee('هذه الفئة غير متاحة.');
    }

    public function test_hidden_entities_return_404_without_403(): void
    {
        ['vendor' => $vendor, 'product' => $product, 'category' => $category] = $this->publishSimple();

        $this->actingAs($vendor);
        app(ProductPublicationService::class)->unpublish($product->fresh());
        Auth::logout();

        $this->get(route('storefront.product', $product->slug))->assertNotFound();

        $category->update(['is_active' => false]);
        $this->get(route('storefront.category', $category->slug))->assertNotFound();

        $vendor->vendor->store->forceFill(['status' => 'suspended'])->save();
        $this->get(route('storefront.store', $vendor->vendor->store->slug))->assertNotFound();
    }

    public function test_variable_pdp_uses_public_selector_payload_and_no_js_fallback(): void
    {
        $vendor = $this->createVendorUser();
        $category = Category::factory()->create(['is_active' => true]);
        $attribute = Attribute::factory()->create(['code' => 'color', 'is_active' => true]);
        $red = AttributeValue::factory()->for($attribute)->create(['code' => 'red', 'is_active' => true]);
        $blue = AttributeValue::factory()->for($attribute)->create(['code' => 'blue', 'is_active' => true]);
        $attribute->translations()->updateOrCreate(['locale' => 'ar'], ['name' => 'اللون']);
        $attribute->translations()->updateOrCreate(['locale' => 'en'], ['name' => 'Color']);
        $red->translations()->updateOrCreate(['locale' => 'ar'], ['name' => 'أحمر']);
        $red->translations()->updateOrCreate(['locale' => 'en'], ['name' => 'Red']);
        $blue->translations()->updateOrCreate(['locale' => 'ar'], ['name' => 'أزرق']);
        $blue->translations()->updateOrCreate(['locale' => 'en'], ['name' => 'Blue']);

        $this->actingAs($vendor);
        $product = app(ProductService::class)->createVariableDraft($vendor->vendor->store, [
            'type' => 'variable',
            'category_id' => $category->id,
            'currency_code' => 'SYP',
            'translations' => [
                'ar' => ['name' => 'قميص متغير'],
                'en' => ['name' => 'Variable Shirt'],
            ],
            'attributes' => [
                ['attribute_id' => $attribute->id, 'value_ids' => [$red->id, $blue->id]],
            ],
            'variants' => [
                [
                    'value_ids' => [$red->id],
                    'sku' => 'PUBLIC-RED',
                    'price' => '100',
                    'quantity' => 5,
                    'is_default' => true,
                ],
                [
                    'value_ids' => [$blue->id],
                    'sku' => 'PUBLIC-BLUE',
                    'price' => '120',
                    'quantity' => 0,
                    'is_default' => false,
                ],
            ],
        ]);
        app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());
        app(ProductPublicationService::class)->publish($product->fresh());
        Auth::logout();

        $attributeOptions = app(StorefrontFilterOptionsService::class)->get('en')['attributes'];
        $this->assertSame('color', $attributeOptions[0]['code']);
        $this->assertSame(['red', 'blue'], array_column($attributeOptions[0]['values'], 'code'));

        $this->withCookie(Locale::COOKIE, 'en')
            ->get(route('storefront.product', $product->slug))
            ->assertOk()
            ->assertSee('storefrontVariantSelector', false)
            ->assertSee('Available combinations')
            ->assertSee('Red')
            ->assertSee('Blue')
            ->assertSee(':disabled="!isValueAvailable', false)
            ->assertSee(':aria-disabled="(!isValueAvailable', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('priceRangeLabel', false)
            ->assertDontSee('PUBLIC-RED')
            ->assertDontSee('PUBLIC-BLUE')
            ->assertSee('name="quantity"', false)
            ->assertSee('Add to cart')
            ->assertSee('x-bind:disabled="!selectedVariant"', false);
    }

    public function test_effective_filter_chips_remove_currency_dependencies_and_keep_unresolved_filters_visible(): void
    {
        $this->publishSimple([
            'currency_code' => 'USD',
            'price' => '12.50',
            'name_en' => 'Filtered Product',
            'sku' => 'FILTER-CHIP',
        ]);

        $response = $this->withCookie(Locale::COOKIE, 'en')->get(route('storefront.search', [
            'q' => 'Filtered',
            'currency' => 'USD',
            'min_price' => '10',
            'max_price' => '20',
            'sort' => 'price_asc',
        ]));
        $response->assertOk();

        $html = html_entity_decode($response->getContent(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $matches = [];
        // Currency chips render the localized currency label (e.g. "US Dollar (USD)"), not the bare code.
        $matches = [];
        $this->assertSame(
            1,
            preg_match(
                '/href="([^"]+)"[^>]*>\s*<span>[^<]*\bUSD\b[^<]*<\/span>\s*<span[^>]*>[^<]*<\/span>\s*<span class="sr-only">/su',
                $html,
                $matches,
            ),
        );
        parse_str((string) parse_url(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_QUERY), $query);
        $this->assertSame(['q' => 'Filtered'], $query);
        $this->assertArrayNotHasKey('currency', $query);
        $this->assertArrayNotHasKey('min_price', $query);
        $this->assertArrayNotHasKey('max_price', $query);
        $this->assertArrayNotHasKey('sort', $query);

        $invalidRange = $this->withCookie(Locale::COOKIE, 'en')->get(route('storefront.search', [
            'currency' => 'USD',
            'min_price' => '30',
            'max_price' => '10',
        ]));
        $invalidRange
            ->assertOk()
            ->assertDontSee('name="min_price" value="30"', false)
            ->assertDontSee('name="max_price" value="10"', false);

        $this->withCookie(Locale::COOKIE, 'en')
            ->get(route('storefront.search', ['category' => 'missing_filter']))
            ->assertOk()
            ->assertSee('role="alert"', false)
            ->assertSee('<option value="missing_filter" selected>', false)
            ->assertSee(__('No matching products'));
    }

    public function test_public_page_cap_is_deterministic_and_page_two_contains_the_twenty_fifth_product(): void
    {
        $vendor = $this->createVendorUser();
        $category = Category::factory()->create(['is_active' => true]);
        $products = Product::factory()->count(25)->create([
            'store_id' => $vendor->vendor->store->id,
            'category_id' => $category->id,
            'currency_code' => 'SYP',
        ]);
        foreach ($products as $product) {
            $product->forceFill([
                'status' => 'published',
                'published_at' => now(),
            ])->saveQuietly();
        }

        $pageTwo = app(StorefrontBrowseService::class)->browse(['page' => '2']);
        $this->assertSame(2, $pageTwo->paginator->currentPage());
        $this->assertSame(25, $pageTwo->paginator->total());
        $this->assertCount(1, $pageTwo->paginator->items());

        $overflow = app(StorefrontBrowseService::class)->browse(['page' => PHP_INT_MAX]);
        $this->assertSame(StorefrontBrowseService::MAX_PUBLIC_PAGE, $overflow->paginator->currentPage());
    }

    public function test_filter_options_only_include_represented_hierarchy_and_entities(): void
    {
        $vendor = $this->createVendorUser();
        $root = Category::factory()->create([
            'parent_id' => null,
            'slug' => 'represented-root',
            'is_active' => true,
        ]);
        $leaf = Category::factory()->create([
            'parent_id' => $root->id,
            'slug' => 'represented-leaf',
            'is_active' => true,
        ]);
        Category::factory()->create(['slug' => 'unrepresented-category', 'is_active' => true]);
        $representedBrand = Brand::factory()->create(['slug' => 'represented-brand', 'is_active' => true]);
        Brand::factory()->create(['slug' => 'unrepresented-brand', 'is_active' => true]);

        $product = Product::factory()->create([
            'store_id' => $vendor->vendor->store->id,
            'category_id' => $leaf->id,
            'brand_id' => $representedBrand->id,
            'currency_code' => 'SYP',
        ]);
        $product->forceFill(['status' => 'published', 'published_at' => now()])->saveQuietly();

        $options = app(StorefrontFilterOptionsService::class)->get('en');
        $this->assertSame(
            ['represented-root', 'represented-leaf'],
            array_column($options['categories'], 'slug'),
        );
        $this->assertSame('Represented Root › Represented Leaf', $options['categories'][1]['label']);
        $this->assertSame(['represented-root'], array_column($options['navigation'], 'slug'));
        $this->assertSame(['represented-brand'], array_column($options['brands'], 'slug'));
        $this->assertSame([$vendor->vendor->store->slug], array_column($options['stores'], 'slug'));
        $this->assertSame(['SYP'], array_column($options['currencies'], 'code'));
        $this->assertStringNotContainsString('"count"', (string) json_encode($options));
        $this->assertStringNotContainsString('"rating"', (string) json_encode($options));
    }

    public function test_navigation_and_home_services_return_focused_bounded_payloads(): void
    {
        $busyVendor = $this->createVendorUser();
        $quietVendor = $this->createVendorUser();
        $busyVendor->vendor->store->update(['name' => 'Alpha Store']);
        $quietVendor->vendor->store->update(['name' => 'Beta Store']);
        $category = Category::factory()->create([
            'parent_id' => null,
            'slug' => 'home-root',
            'is_active' => true,
        ]);

        $publishedAt = now();
        $busyProducts = Product::factory()->count(9)->create([
            'store_id' => $busyVendor->vendor->store->id,
            'category_id' => $category->id,
            'currency_code' => 'SYP',
        ]);
        $quietProduct = Product::factory()->create([
            'store_id' => $quietVendor->vendor->store->id,
            'category_id' => $category->id,
            'currency_code' => 'SYP',
        ]);
        foreach ($busyProducts->push($quietProduct) as $product) {
            $product->forceFill([
                'status' => 'published',
                'published_at' => $publishedAt,
            ])->saveQuietly();
        }

        $navigation = app(StorefrontNavigationService::class)->get('en');
        $this->assertSame(['slug', 'name', 'url'], array_keys($navigation[0]));
        $this->assertSame('home-root', $navigation[0]['slug']);

        $home = app(StorefrontHomeService::class)->get('en');
        $this->assertIsArray($home['products']);
        $this->assertCount(StorefrontHomeService::PRODUCT_LIMIT, $home['products']);
        $this->assertSame($home['products'][0], $home['hero_product']);
        $this->assertSame('Alpha Store', $home['stores'][0]['name']);
        $this->assertSame('home-root', $home['categories'][0]['slug']);
    }

    public function test_gallery_initializes_from_primary_image_even_when_it_is_not_first(): void
    {
        ['vendor' => $vendor, 'product' => $product] = $this->publishSimple([
            'name_en' => 'Gallery Product',
            'sku' => 'GALLERY-PRIMARY',
        ]);

        $this->actingAs($vendor);
        $second = app(ProductImageService::class)->upload(
            $product->fresh(),
            $this->makeProductImageUpload(width: 800, height: 600, clientName: 'second.jpg'),
        );
        app(ProductImageService::class)->updateAltTexts($product->fresh(), $second, [
            'en' => ['alt_text' => 'Primary gallery image'],
        ]);
        app(ProductImageService::class)->setPrimary($product->fresh(), $second);
        Auth::logout();

        $this->withCookie(Locale::COOKIE, 'en')
            ->get(route('storefront.product', $product->slug))
            ->assertOk()
            ->assertSee('active: 1', false)
            ->assertSee('Primary gallery image')
            ->assertSee('aria-current="true"', false)
            ->assertSee(__('Image unavailable'))
            ->assertSee('loading="eager"', false)
            ->assertSee('fetchpriority="high"', false);
    }

    public function test_corrupt_product_with_more_than_the_live_variant_cap_fails_closed(): void
    {
        ['product' => $product] = $this->publishSimple(['sku' => 'CORRUPT-BASE']);
        $rows = [];
        for ($index = 1; $index <= ProductVariant::MAX_LIVE_PER_PRODUCT; $index++) {
            $rows[] = [
                'product_id' => $product->id,
                'store_id' => $product->store_id,
                'sku' => 'CORRUPT-'.$index,
                'combination_key' => 'corrupt-'.$index,
                'price_amount_minor' => 100,
                'compare_at_amount_minor' => null,
                'quantity' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ];
        }
        DB::table('product_variants')->insert($rows);

        $this->assertSame(
            ProductVariant::MAX_LIVE_PER_PRODUCT + 1,
            $product->variants()->count(),
        );
        $this->get(route('storefront.product', $product->slug))->assertNotFound();
    }

    public function test_storefront_head_dialogs_and_no_js_fallback_are_accessible(): void
    {
        ['vendor' => $vendor, 'product' => $product, 'category' => $category] = $this->publishSimple([
            'name_en' => 'Accessible Product',
            'sku' => 'A11Y-SEO',
        ]);

        $home = $this->withCookie(Locale::COOKIE, 'en')->get(route('home'));
        $home
            ->assertOk()
            ->assertSee('<meta name="description"', false)
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta property="og:url" content="'.route('home').'">', false)
            ->assertSee('href="#main"', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('aria-controls="storefront-mobile-navigation"', false)
            ->assertDontSee('application/ld+json', false)
            ->assertDontSee('hreflang=', false)
            ->assertDontSee(route('design-system'));

        $search = $this->withCookie(Locale::COOKIE, 'en')->get(route('storefront.search'));
        $search
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertSee('id="catalog-filter-dialog"', false)
            ->assertSee('role="dialog"', false)
            ->assertSee('aria-modal="true"', false)
            ->assertSee('<noscript>', false)
            ->assertSee('x-model="currency"', false)
            ->assertSee(':disabled="!currency"', false);

        $this->withCookie(Locale::COOKIE, 'en')
            ->get(route('storefront.category', $category->slug))
            ->assertOk()
            ->assertSee('<meta name="robots" content="index,follow">', false);
        $this->withCookie(Locale::COOKIE, 'en')
            ->get(route('storefront.category', [
                'slug' => $category->slug,
                'brand' => 'missing-brand',
            ]))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
        $this->withCookie(Locale::COOKIE, 'en')
            ->get(route('storefront.product', $product->slug))
            ->assertOk()
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertSee('<meta property="og:url" content="'.route('storefront.product', $product->slug).'">', false);
        $this->get(route('storefront.store', $vendor->vendor->store->slug))->assertOk();
    }

    public function test_design_system_is_hidden_outside_local_testing_except_for_staff(): void
    {
        $originalEnvironment = $this->app->environment();
        $this->app['env'] = 'production';

        try {
            $this->get(route('design-system'))->assertNotFound();

            $staff = User::factory()->admin()->create();
            $this->actingAs($staff)->get(route('design-system'))->assertOk();
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
    }

    public function test_public_route_query_counts_have_absolute_budgets(): void
    {
        ['vendor' => $vendor, 'product' => $product, 'category' => $category] = $this->publishSimple([
            'sku' => 'QUERY-BUDGET',
        ]);

        $routes = [
            'home' => [route('home'), 15],
            'search' => [route('storefront.search'), 25],
            'category' => [route('storefront.category', $category->slug), 30],
            'store' => [route('storefront.store', $vendor->vendor->store->slug), 30],
            'product' => [route('storefront.product', $product->slug), 50],
        ];

        foreach ($routes as $name => [$url, $budget]) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->withCookie(Locale::COOKIE, 'en')->get($url)->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            $this->assertLessThanOrEqual($budget, $count, "{$name} exceeded its {$budget}-query budget with {$count}.");
        }
    }

    public function test_http_query_count_is_stable_as_cards_grow(): void
    {
        $sharedVendor = $this->createVendorUser();
        $sharedCategory = Category::factory()->create(['is_active' => true]);
        $sharedBrand = Brand::factory()->create(['is_active' => true]);

        $this->publishSimple([
            'vendor' => $sharedVendor,
            'category' => $sharedCategory,
            'brand' => $sharedBrand,
            'sku' => 'COUNT-1',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->withCookie(Locale::COOKIE, 'en')->get(route('storefront.search'))->assertOk();
        $small = count(DB::getQueryLog());

        DB::disableQueryLog();
        for ($i = 2; $i <= 7; $i++) {
            $this->publishSimple([
                'vendor' => $sharedVendor,
                'category' => $sharedCategory,
                'brand' => $sharedBrand,
                'sku' => 'COUNT-'.$i,
                'name_en' => 'Count Product '.$i,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->withCookie(Locale::COOKIE, 'en')->get(route('storefront.search'))->assertOk();
        $large = count(DB::getQueryLog());

        $this->assertSame($small, $large);
    }
}
