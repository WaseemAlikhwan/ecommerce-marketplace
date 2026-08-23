<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
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
use App\Support\VendorProductReadinessState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VendorProductPublicationUiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{vendor: User, product: Product, category: Category}
     */
    private function makeDraft(array $overrides = [], bool $withImage = false): array
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);

        $category = Category::factory()->create(['is_active' => true]);
        $brand = Brand::factory()->create(['is_active' => true]);

        $payload = array_replace_recursive([
            'type' => 'simple',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'currency_code' => 'SYP',
            'sku' => 'UI-'.uniqid(),
            'price' => '1000',
            'quantity' => 1,
            'translations' => [
                'ar' => ['name' => 'منتج واجهة'],
                'en' => ['name' => 'UI Product'],
            ],
        ], $overrides);

        $product = app(ProductService::class)->createSimpleDraft($vendor->vendor->store, $payload);

        if ($withImage) {
            app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());
        }

        return [
            'vendor' => $vendor,
            'product' => $product->fresh(),
            'category' => $category,
            'brand' => $brand,
        ];
    }

    public function test_incomplete_draft_shows_checklist_and_disabled_publish(): void
    {
        ['vendor' => $vendor, 'product' => $product] = $this->makeDraft([
            'translations' => [
                'en' => ['name' => 'English only'],
                'ar' => ['name' => ''],
            ],
        ]);

        $this->actingAs($vendor)
            ->get(route('vendor.products.edit', $product))
            ->assertOk()
            ->assertSee('data-product-readiness', false)
            ->assertSee('data-publish-action', false)
            ->assertSee('remainingTitle', false)
            ->assertSee('missing_translation_ar', false)
            ->assertSee('id="product-content"', false)
            ->assertSee('id="product-gallery"', false)
            ->assertSee('id="product-details"', false)
            ->assertSee('id="product-variants"', false)
            ->assertDontSee('Publishing comes later')
            ->assertSee('data-publish-action', false);

        $html = $this->actingAs($vendor)->get(route('vendor.products.edit', $product))->getContent();
        $this->assertMatchesRegularExpression('/data-publish-action[\s\S]*?<button[^>]*\bdisabled\b/i', $html);
        $this->assertSame(1, substr_count($html, 'id="vendor-product-form"'));
        $formStart = strpos($html, 'id="vendor-product-form"');
        $formEnd = strpos($html, '</form>', $formStart);
        $publishPos = strpos($html, 'data-publish-action');
        $this->assertNotFalse($formStart);
        $this->assertNotFalse($formEnd);
        $this->assertNotFalse($publishPos);
        $this->assertGreaterThan($formEnd, $publishPos);
    }

    public function test_complete_draft_enables_publish_and_published_shows_unpublish(): void
    {
        ['vendor' => $vendor, 'product' => $product] = $this->makeDraft(withImage: true);

        $response = $this->actingAs($vendor)->get(route('vendor.products.edit', $product));
        $response->assertOk()
            ->assertSee('readyTitle', false)
            ->assertSee('isPublishable\u0022:true', false)
            ->assertSee('data-publish-action', false);

        $html = $response->getContent();
        $this->assertDoesNotMatchRegularExpression(
            '/data-publish-action[\s\S]*?<button[^>]*\sdisabled(=|\s|>)/i',
            $html,
        );

        $this->actingAs($vendor)
            ->post(route('vendor.products.publish', $product))
            ->assertRedirect(route('vendor.products.edit', $product));

        $this->assertSame(ProductStatus::Published, $product->fresh()->status);

        $published = $this->actingAs($vendor)->get(route('vendor.products.edit', $product->fresh()));
        $published->assertOk()
            ->assertSee('data-unpublish-action', false)
            ->assertSee('eligible', false)
            ->assertSee('firstPublished', false)
            ->assertSee('status\u0022:\u0022published', false)
            ->assertDontSee('data-publish-action');
    }

    public function test_unpublished_can_republish_and_contextual_hidden_warning(): void
    {
        ['vendor' => $vendor, 'product' => $product, 'category' => $category] = $this->makeDraft(withImage: true);
        app(ProductPublicationService::class)->publish($product->fresh());
        app(ProductPublicationService::class)->unpublish($product->fresh());

        $this->actingAs($vendor)
            ->get(route('vendor.products.edit', $product->fresh()))
            ->assertOk()
            ->assertSee('data-publish-action', false)
            ->assertSee('status\u0022:\u0022unpublished', false)
            ->assertSee('isPublishable\u0022:true', false);

        app(ProductPublicationService::class)->publish($product->fresh());
        $category->forceFill(['is_active' => false])->save();

        $this->actingAs($vendor)
            ->get(route('vendor.products.edit', $product->fresh()))
            ->assertOk()
            ->assertSee('storefrontEligibility\u0022:\u0022hidden', false)
            ->assertSee('inactive_category', false)
            ->assertSee('hiddenHint', false);
    }

    public function test_suspended_and_archived_have_no_publication_actions(): void
    {
        ['vendor' => $vendor, 'product' => $product] = $this->makeDraft(withImage: true);
        app(ProductPublicationService::class)->publish($product->fresh());

        $product->forceFill(['status' => ProductStatus::Suspended])->save();
        $this->actingAs($vendor)
            ->get(route('vendor.products.edit', $product->fresh()))
            ->assertOk()
            ->assertSee('status\u0022:\u0022suspended', false)
            ->assertSee('readOnlyLifecycle\u0022:true', false)
            ->assertDontSee('data-publish-action')
            ->assertDontSee('data-unpublish-action');

        $product->forceFill(['status' => ProductStatus::Archived])->save();
        $this->actingAs($vendor)
            ->get(route('vendor.products.edit', $product->fresh()))
            ->assertOk()
            ->assertSee('status\u0022:\u0022archived', false)
            ->assertSee('readOnlyLifecycle\u0022:true', false)
            ->assertDontSee('data-publish-action')
            ->assertDontSee('data-unpublish-action')
            ->assertDontSee('showPublishControl\u0022:true');
    }

    public function test_variable_topology_notice_and_authorization_omits_routes(): void
    {
        Storage::fake('public');
        $owner = $this->createVendorUser();
        $other = $this->createVendorUser();
        $this->actingAs($owner);

        $category = Category::factory()->create(['is_active' => true]);
        $color = Attribute::factory()->create(['is_active' => true]);
        $red = AttributeValue::factory()->for($color)->create(['is_active' => true]);

        $product = app(ProductService::class)->createVariableDraft($owner->vendor->store, [
            'type' => 'variable',
            'category_id' => $category->id,
            'currency_code' => 'SYP',
            'translations' => [
                'ar' => ['name' => 'قميص'],
                'en' => ['name' => 'Shirt'],
            ],
            'attributes' => [
                ['attribute_id' => $color->id, 'value_ids' => [$red->id]],
            ],
            'variants' => [[
                'value_ids' => [$red->id],
                'sku' => 'UI-VAR-'.uniqid(),
                'price' => '80',
                'quantity' => 1,
                'is_default' => true,
            ]],
        ]);
        app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());
        app(ProductPublicationService::class)->publish($product->fresh());

        $this->actingAs($owner)
            ->get(route('vendor.products.edit', $product->fresh()))
            ->assertOk()
            ->assertSee('topologyFrozen\u0022:true', false)
            ->assertSee('id="product-matrix"', false);

        $this->actingAs($other)
            ->get(route('vendor.products.edit', $product))
            ->assertForbidden();
    }

    public function test_locale_rendering_and_old_input_marks_form_dirty(): void
    {
        ['vendor' => $vendor, 'product' => $product] = $this->makeDraft(withImage: true);

        $this->actingAs($vendor)
            ->post('/locale', ['locale' => 'ar'])
            ->assertRedirect();

        $this->actingAs($vendor)
            ->get(route('vendor.products.edit', $product))
            ->assertOk()
            ->assertSee('جاهزية النشر', false);

        $this->actingAs($vendor)
            ->post('/locale', ['locale' => 'en'])
            ->assertRedirect();

        $this->actingAs($vendor)
            ->get(route('vendor.products.edit', $product))
            ->assertOk()
            ->assertSee('Publication readiness', false);

        $html = $this->followingRedirects()
            ->actingAs($vendor)
            ->from(route('vendor.products.edit', $product))
            ->put(route('vendor.products.update', $product), [
                'type' => 'simple',
                'slug' => $product->slug,
                'category_id' => $product->category_id,
                'brand_id' => $product->brand_id,
                'currency_code' => $product->currency_code,
                'sku' => $product->defaultVariant->sku,
                'price' => '1000',
                'quantity' => 1,
                'translations' => [
                    'ar' => ['name' => ''],
                    'en' => ['name' => ''],
                ],
            ])
            ->assertOk()
            ->getContent();

        $this->assertTrue(
            str_contains($html, 'formInitiallyDirty\u0022:true')
            || str_contains($html, 'formInitiallyDirty":true'),
            'Expected formInitiallyDirty true in readiness bootstrap after validation redirect.',
        );
        $this->assertStringContainsString('data-product-form-dirty="1"', $html);
    }

    public function test_ajax_upload_and_remove_sync_readiness_payload(): void
    {
        ['vendor' => $vendor, 'product' => $product] = $this->makeDraft();

        $upload = $this->actingAs($vendor)
            ->postJson(route('vendor.products.images.store', $product), [
                'image' => $this->makeProductImageUpload(),
            ])
            ->assertOk()
            ->assertJsonPath('readiness.isPublishable', true)
            ->assertJsonStructure(['readiness' => ['groups', 'actions', 'isPublishable', 'publicationIssueCount']]);

        $codes = collect($upload->json('readiness.groups'))
            ->flatMap(fn (array $group) => collect($group['issues'])->pluck('code'))
            ->all();
        $this->assertNotContains('missing_product_image', $codes);
        $this->assertArrayHasKey('publish', (array) $upload->json('readiness.actions'));

        $imageId = (int) $upload->json('image_id');

        $remove = $this->actingAs($vendor)
            ->deleteJson(route('vendor.products.images.destroy', [$product, $imageId]))
            ->assertOk()
            ->assertJsonPath('readiness.isPublishable', false);

        $issueCodes = collect($remove->json('readiness.groups'))
            ->flatMap(fn (array $group) => collect($group['issues'])->pluck('code'))
            ->all();
        $this->assertContains('missing_product_image', $issueCodes);
    }

    public function test_reorder_and_alt_responses_omit_readiness_and_routes_respect_auth(): void
    {
        ['vendor' => $vendor, 'product' => $product] = $this->makeDraft(withImage: true);
        $image = $product->images()->firstOrFail();

        $this->actingAs($vendor)
            ->putJson(route('vendor.products.images.reorder', $product), [
                'image_ids' => [$image->id],
            ])
            ->assertOk()
            ->assertJsonMissingPath('readiness');

        $this->actingAs($vendor)
            ->putJson(route('vendor.products.images.translations', [$product, $image]), [
                'translations' => [
                    'ar' => ['alt_text' => 'عربي'],
                    'en' => ['alt_text' => 'English'],
                ],
            ])
            ->assertOk()
            ->assertJsonMissingPath('readiness');

        $upload = $this->actingAs($vendor)
            ->postJson(route('vendor.products.images.store', $product), [
                'image' => $this->makeProductImageUpload('png', 420, 420, 'second.png'),
            ])
            ->assertOk();

        $this->assertNotEmpty($upload->json('gallery.images.0.routes'));
        $this->assertArrayHasKey('publish', (array) $upload->json('readiness.actions'));
    }

    public function test_index_query_count_constant_for_same_vendor_products(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);
        $category = Category::factory()->create(['is_active' => true]);

        $create = function (string $sku) use ($vendor, $category): Product {
            return app(ProductService::class)->createSimpleDraft($vendor->vendor->store, [
                'type' => 'simple',
                'category_id' => $category->id,
                'currency_code' => 'SYP',
                'sku' => $sku,
                'price' => '10',
                'quantity' => 1,
                'translations' => [
                    'ar' => ['name' => 'منتج'],
                    'en' => ['name' => 'Product '.$sku],
                ],
            ])->fresh();
        };

        $create('IDX-1');
        $this->actingAs($vendor)->post('/locale', ['locale' => 'en'])->assertRedirect();

        DB::flushQueryLog();
        DB::connection()->enableQueryLog();
        $one = $this->actingAs($vendor)->get(route('vendor.products'))->assertOk();
        $queriesOne = count(DB::getQueryLog());
        DB::connection()->disableQueryLog();
        $one->assertSee('Product IDX-1', false);

        for ($i = 2; $i <= 20; $i++) {
            $create('IDX-'.$i);
        }

        DB::flushQueryLog();
        DB::connection()->enableQueryLog();
        $many = $this->actingAs($vendor)->get(route('vendor.products'))->assertOk();
        $queriesMany = count(DB::getQueryLog());
        DB::connection()->disableQueryLog();

        $many->assertSee('Product IDX-1', false)->assertSee('Product IDX-20', false);
        $this->assertLessThanOrEqual($queriesOne + 2, $queriesMany);
        $many->assertDontSee('data-product-readiness');
        $many->assertSee('Open a product to review readiness and publish.', false);
    }

    public function test_edit_readiness_query_count_stable_with_extra_images(): void
    {
        ['vendor' => $vendor, 'product' => $product] = $this->makeDraft(withImage: true);

        DB::flushQueryLog();
        DB::connection()->enableQueryLog();
        $this->actingAs($vendor)->get(route('vendor.products.edit', $product))->assertOk();
        $queriesA = count(DB::getQueryLog());
        DB::connection()->disableQueryLog();

        app(ProductImageService::class)->upload($product->fresh(), $this->makeProductImageUpload('png', 400, 400, 'extra.png'));
        app(ProductImageService::class)->upload($product->fresh(), $this->makeProductImageUpload('webp', 400, 400, 'extra2.webp'));

        DB::flushQueryLog();
        DB::connection()->enableQueryLog();
        $this->actingAs($vendor)->get(route('vendor.products.edit', $product->fresh()))->assertOk();
        $queriesB = count(DB::getQueryLog());
        DB::connection()->disableQueryLog();

        $this->assertLessThanOrEqual($queriesA + 3, $queriesB);
    }

    public function test_inactive_current_category_brand_currency_render_and_preserve(): void
    {
        ['vendor' => $vendor, 'product' => $product, 'category' => $category, 'brand' => $brand] = $this->makeDraft(withImage: true);

        $category->forceFill(['is_active' => false])->save();
        $brand->forceFill(['is_active' => false])->save();
        Currency::query()->where('code', 'SYP')->update(['is_active' => false]);

        $this->actingAs($vendor)->post('/locale', ['locale' => 'en'])->assertRedirect();

        $html = $this->actingAs($vendor)
            ->get(route('vendor.products.edit', $product->fresh()))
            ->assertOk()
            ->assertSee('Inactive — current selection', false)
            ->getContent();

        $this->assertTrue(
            (bool) preg_match('/<option[^>]*value="'.$category->id.'"[^>]*\bselected\b/i', $html)
            || (bool) preg_match('/<option[^>]*\bselected\b[^>]*value="'.$category->id.'"/i', $html),
            'Inactive category option should be selected',
        );
        $this->assertTrue(
            (bool) preg_match('/<option[^>]*value="'.$brand->id.'"[^>]*\bselected\b/i', $html)
            || (bool) preg_match('/<option[^>]*\bselected\b[^>]*value="'.$brand->id.'"/i', $html),
            'Inactive brand option should be selected',
        );
        $this->assertTrue(
            (bool) preg_match('/<option[^>]*value="SYP"[^>]*\bselected\b/i', $html)
            || (bool) preg_match('/<option[^>]*\bselected\b[^>]*value="SYP"/i', $html),
            'Inactive currency option should be selected',
        );

        $this->actingAs($vendor)
            ->put(route('vendor.products.update', $product), [
                'type' => 'simple',
                'slug' => $product->slug,
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'currency_code' => 'SYP',
                'sku' => $product->defaultVariant->sku,
                'price' => '1800',
                'quantity' => 2,
                'translations' => [
                    'ar' => ['name' => 'منتج واجهة', 'short_description' => null, 'description' => null],
                    'en' => ['name' => 'UI Product', 'short_description' => null, 'description' => null],
                ],
            ])
            ->assertRedirect(route('vendor.products.edit', $product));

        $fresh = $product->fresh();
        $this->assertSame($category->id, $fresh->category_id);
        $this->assertSame($brand->id, $fresh->brand_id);
        $this->assertSame('SYP', $fresh->currency_code);
        $this->assertSame(1800, $fresh->defaultVariant->price_amount_minor);

        Currency::query()->where('code', 'SYP')->update(['is_active' => true]);
    }

    public function test_presenter_performs_zero_queries_and_omits_unused_action_routes(): void
    {
        ['product' => $product] = $this->makeDraft(withImage: true);
        $product = $product->fresh();
        $result = app(ProductReadinessService::class)->evaluate($product);

        DB::flushQueryLog();
        DB::connection()->enableQueryLog();
        $payload = VendorProductReadinessState::from($product, $result, true, false)->payload();
        $queries = count(DB::getQueryLog());
        DB::connection()->disableQueryLog();

        $this->assertSame(0, $queries);
        $this->assertArrayHasKey('publish', $payload['actions']);
        $this->assertArrayNotHasKey('unpublish', $payload['actions']);

        app(ProductPublicationService::class)->publish($product->fresh());
        $published = $product->fresh();
        $publishedPayload = VendorProductReadinessState::from(
            $published,
            app(ProductReadinessService::class)->evaluate($published),
            true,
            true,
        )->payload();

        $this->assertArrayHasKey('unpublish', $publishedPayload['actions']);
        $this->assertArrayNotHasKey('publish', $publishedPayload['actions']);
        $this->assertNotEmpty($publishedPayload['publishedAtLabel']);
        $this->assertNotEmpty($publishedPayload['publishedAt']);
    }

    public function test_noscript_summary_and_old_input_disables_publish_server_side(): void
    {
        ['vendor' => $vendor, 'product' => $product] = $this->makeDraft([
            'translations' => [
                'en' => ['name' => 'English only'],
                'ar' => ['name' => ''],
            ],
        ], withImage: true);

        $this->actingAs($vendor)
            ->get(route('vendor.products.edit', $product))
            ->assertOk()
            ->assertSee('data-readiness-noscript', false)
            ->assertSee('missing_translation_ar', false);

        $html = $this->followingRedirects()
            ->actingAs($vendor)
            ->from(route('vendor.products.edit', $product))
            ->put(route('vendor.products.update', $product), [
                'type' => 'simple',
                'slug' => $product->slug,
                'category_id' => $product->category_id,
                'brand_id' => $product->brand_id,
                'currency_code' => $product->currency_code,
                'sku' => $product->defaultVariant->sku,
                'price' => '1000',
                'quantity' => 1,
                'translations' => [
                    'ar' => ['name' => ''],
                    'en' => ['name' => ''],
                ],
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-product-form-dirty="1"', $html);
        $this->assertMatchesRegularExpression('/data-publish-action[\s\S]*?<button[^>]*\bdisabled\b/i', $html);
        $this->assertStringContainsString('data-readiness-noscript', $html);
    }

    public function test_create_form_excludes_inactive_options(): void
    {
        $vendor = $this->createVendorUser();
        $inactiveBrand = Brand::factory()->create(['is_active' => false, 'slug' => 'inactive-brand-'.uniqid()]);
        Currency::query()->where('code', 'USD')->update(['is_active' => false]);

        $html = $this->actingAs($vendor)
            ->get(route('vendor.products.create'))
            ->assertOk()
            ->assertDontSee('Inactive — current selection')
            ->getContent();

        $this->assertStringNotContainsString('value="'.$inactiveBrand->id.'"', $html);
        $this->assertStringNotContainsString('value="USD"', $html);
        Currency::query()->where('code', 'USD')->update(['is_active' => true]);
    }

    public function test_s7b_translation_parity(): void
    {
        $en = json_decode(File::get(lang_path('en.json')), true);
        $ar = json_decode(File::get(lang_path('ar.json')), true);
        $this->assertEqualsCanonicalizing(array_keys($en), array_keys($ar));

        foreach ([
            'Publication readiness',
            'Ready to publish',
            'Published but hidden by catalog rules',
            'Save or discard changes first',
            'Eligible for storefront',
            'Inactive — current selection',
            'Reload saved version',
        ] as $key) {
            $this->assertArrayHasKey($key, $en);
            $this->assertArrayHasKey($key, $ar);
        }
    }
}
