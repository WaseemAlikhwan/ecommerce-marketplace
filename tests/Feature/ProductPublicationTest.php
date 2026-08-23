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
use App\Models\ProductAttribute;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductImageService;
use App\Services\ProductPublicationService;
use App\Services\ProductReadinessService;
use App\Services\ProductService;
use App\Support\CombinationKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductPublicationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{vendor: User, product: Product, category: Category}
     */
    private function makePublishableSimple(array $overrides = []): array
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
            'sku' => 'PUB-S-'.uniqid(),
            'price' => '1000',
            'compare_at_price' => null,
            'quantity' => 0,
            'translations' => [
                'ar' => ['name' => 'منتج منشور'],
                'en' => ['name' => 'Publishable Product'],
            ],
        ], $overrides);

        $product = app(ProductService::class)->createSimpleDraft($vendor->vendor->store, $payload);
        app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());

        return [
            'vendor' => $vendor,
            'product' => $product->fresh(['translations', 'images', 'defaultVariant', 'category', 'brand', 'currency', 'store.vendor']),
            'category' => $category,
            'brand' => $brand,
        ];
    }

    /**
     * @return array{vendor: User, product: Product, attrs: array<string, mixed>}
     */
    private function makePublishableVariable(): array
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);

        $category = Category::factory()->create(['is_active' => true]);
        $color = Attribute::factory()->create(['code' => 'color-'.uniqid(), 'is_active' => true]);
        $size = Attribute::factory()->create(['code' => 'size-'.uniqid(), 'is_active' => true]);
        $red = AttributeValue::factory()->for($color)->create(['code' => 'red-'.uniqid(), 'is_active' => true]);
        $blue = AttributeValue::factory()->for($color)->create(['code' => 'blue-'.uniqid(), 'is_active' => true]);
        $small = AttributeValue::factory()->for($size)->create(['code' => 's-'.uniqid(), 'is_active' => true]);
        $medium = AttributeValue::factory()->for($size)->create(['code' => 'm-'.uniqid(), 'is_active' => true]);

        $attrs = compact('color', 'size', 'red', 'blue', 'small', 'medium');

        $product = app(ProductService::class)->createVariableDraft($vendor->vendor->store, [
            'type' => 'variable',
            'category_id' => $category->id,
            'currency_code' => 'SYP',
            'translations' => [
                'ar' => ['name' => 'قميص متغير'],
                'en' => ['name' => 'Variable Shirt'],
            ],
            'attributes' => [
                ['attribute_id' => $color->id, 'value_ids' => [$red->id, $blue->id]],
                ['attribute_id' => $size->id, 'value_ids' => [$small->id, $medium->id]],
            ],
            'variants' => [
                [
                    'value_ids' => [$red->id, $small->id],
                    'sku' => 'VAR-RS-'.uniqid(),
                    'price' => '100',
                    'quantity' => 0,
                    'is_default' => true,
                ],
                [
                    'value_ids' => [$blue->id, $medium->id],
                    'sku' => 'VAR-BM-'.uniqid(),
                    'price' => '120',
                    'quantity' => 2,
                    'is_default' => false,
                ],
            ],
        ]);

        app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());

        return [
            'vendor' => $vendor,
            'product' => $product->fresh(['translations', 'images', 'variants', 'defaultVariant', 'productAttributes.attribute', 'productAttributes.selectedValues.attributeValue']),
            'attrs' => $attrs,
            'category' => $category,
        ];
    }

    public function test_simple_product_publishes_with_zero_stock(): void
    {
        ['vendor' => $vendor, 'product' => $product] = $this->makePublishableSimple();

        $published = app(ProductPublicationService::class)->publish($product);

        $this->assertSame(ProductStatus::Published, $published->status);
        $this->assertNotNull($published->published_at);
        $this->assertSame(0, $published->defaultVariant->quantity);

        $this->actingAs($vendor)
            ->post(route('vendor.products.publish', $product))
            ->assertRedirect(route('vendor.products.edit', $product));
    }

    public function test_variable_subset_matrix_publishes_and_blocks_inactive_first_publication_globals(): void
    {
        ['product' => $product, 'attrs' => $attrs] = $this->makePublishableVariable();

        $published = app(ProductPublicationService::class)->publish($product->fresh());
        $this->assertSame(ProductStatus::Published, $published->status);
        $this->assertCount(2, $published->variants);

        $draft = $this->makePublishableVariable();
        $draft['attrs']['color']->forceFill(['is_active' => false])->save();

        try {
            app(ProductPublicationService::class)->publish($draft['product']->fresh());
            $this->fail('Inactive global attribute should block first publication.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attributes', $exception->errors());
        }
    }

    public function test_republish_allows_unchanged_inactive_historical_globals(): void
    {
        ['product' => $product, 'attrs' => $attrs] = $this->makePublishableVariable();
        $publication = app(ProductPublicationService::class);
        $publication->publish($product->fresh());
        $firstPublishedAt = $product->fresh()->published_at;

        $publication->unpublish($product->fresh());
        $attrs['color']->forceFill(['is_active' => false])->save();
        $attrs['red']->forceFill(['is_active' => false])->save();

        $republished = $publication->publish($product->fresh());
        $this->assertSame(ProductStatus::Published, $republished->status);
        $this->assertTrue($firstPublishedAt->equalTo($republished->published_at));
    }

    public function test_readiness_blocks_missing_names_category_image_and_dependencies(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);
        $readiness = app(ProductReadinessService::class);

        $product = app(ProductService::class)->createSimpleDraft($vendor->vendor->store, [
            'type' => 'simple',
            'sku' => 'READY-1',
            'price' => '10',
            'quantity' => 1,
            'translations' => [
                'en' => ['name' => 'Only English'],
                'ar' => ['name' => ''],
            ],
        ]);

        $codes = collect($readiness->evaluate($product->fresh())->publicationIssues())->pluck('code');
        $this->assertTrue($codes->contains('missing_translation_ar'));
        $this->assertTrue($codes->contains('missing_category'));
        $this->assertTrue($codes->contains('missing_product_image'));

        $parent = Category::factory()->create(['is_active' => true]);
        $leaf = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => true]);
        $product->forceFill([
            'category_id' => $leaf->id,
        ])->save();
        $product->translations()->updateOrCreate(['locale' => 'ar'], ['name' => 'عربي']);
        app(ProductImageService::class)->upload($product->fresh(), $this->makeProductImageUpload());

        $parent->forceFill(['is_active' => false])->save();
        $codes = collect($readiness->evaluate($product->fresh())->publicationIssues())->pluck('code');
        $this->assertTrue($codes->contains('inactive_category_ancestor'));

        $parent->forceFill(['is_active' => true])->save();
        $leaf->forceFill(['is_active' => false])->save();
        $codes = collect($readiness->evaluate($product->fresh())->publicationIssues())->pluck('code');
        $this->assertTrue($codes->contains('inactive_category'));

        $leaf->forceFill(['is_active' => true])->save();
        Currency::query()->where('code', 'SYP')->update(['is_active' => false]);
        $codes = collect($readiness->evaluate($product->fresh())->publicationIssues())->pluck('code');
        $this->assertTrue($codes->contains('inactive_currency'));
        Currency::query()->where('code', 'SYP')->update(['is_active' => true]);

        $brand = Brand::factory()->create(['is_active' => false]);
        $product->forceFill(['brand_id' => $brand->id])->save();
        $codes = collect($readiness->evaluate($product->fresh())->publicationIssues())->pluck('code');
        $this->assertTrue($codes->contains('inactive_brand'));

        $product->forceFill(['brand_id' => null])->save();
        $vendor->vendor->store->forceFill(['status' => StoreStatus::Suspended])->save();
        $codes = collect($readiness->evaluate($product->fresh())->publicationIssues())->pluck('code');
        $this->assertTrue($codes->contains('store_not_sellable'));

        $vendor->vendor->store->forceFill(['status' => StoreStatus::Active])->save();
        $vendor->vendor->forceFill(['status' => VendorStatus::Suspended])->save();
        $codes = collect($readiness->evaluate($product->fresh())->publicationIssues())->pluck('code');
        $this->assertTrue($codes->contains('vendor_not_approved'));
    }

    public function test_invalid_primary_image_and_variant_invariants(): void
    {
        ['product' => $product] = $this->makePublishableSimple();
        $readiness = app(ProductReadinessService::class);

        $product->forceFill(['primary_image_id' => null])->save();
        $codes = collect($readiness->evaluate($product->fresh())->integrityIssues)->pluck('code');
        $this->assertTrue($codes->contains('missing_primary_image'));

        $image = $product->images()->firstOrFail();
        $product->forceFill(['primary_image_id' => $image->id])->save();
        $product->defaultVariant->forceFill(['combination_key' => 'broken'])->save();
        $codes = collect($readiness->evaluate($product->fresh())->integrityIssues)->pluck('code');
        $this->assertTrue($codes->contains('invalid_simple_combination'));
    }

    public function test_status_transitions_idempotence_and_published_at_preservation(): void
    {
        ['vendor' => $vendor, 'product' => $product] = $this->makePublishableSimple();
        $publication = app(ProductPublicationService::class);

        $published = $publication->publish($product->fresh());
        $firstAt = $published->published_at;
        $this->assertNotNull($firstAt);

        $again = $publication->publish($published->fresh());
        $this->assertSame(ProductStatus::Published, $again->status);
        $this->assertTrue($firstAt->equalTo($again->published_at));

        $unpublished = $publication->unpublish($again->fresh());
        $this->assertSame(ProductStatus::Unpublished, $unpublished->status);
        $this->assertTrue($firstAt->equalTo($unpublished->published_at));

        $noop = $publication->unpublish($unpublished->fresh());
        $this->assertSame(ProductStatus::Unpublished, $noop->status);

        $republished = $publication->publish($noop->fresh());
        $this->assertSame(ProductStatus::Published, $republished->status);
        $this->assertTrue($firstAt->equalTo($republished->published_at));

        try {
            $draft = app(ProductService::class)->createSimpleDraft($vendor->vendor->store, [
                'type' => 'simple',
                'sku' => 'DRAFT-UNPUB',
                'price' => '10',
                'quantity' => 1,
                'translations' => ['en' => ['name' => 'X'], 'ar' => ['name' => 'ي']],
            ]);
            $draft->forceFill(['status' => ProductStatus::Draft])->save();
            // Draft cannot unpublish via service when status is Draft.
            $publication->unpublish($draft->fresh());
            $this->fail('Draft unpublish should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
    }

    public function test_suspended_and_archived_cannot_publish_or_unpublish(): void
    {
        ['vendor' => $vendor, 'product' => $product] = $this->makePublishableSimple();
        app(ProductPublicationService::class)->publish($product->fresh());

        $product->forceFill(['status' => ProductStatus::Suspended])->save();
        $this->assertFalse($vendor->can('publish', $product->fresh()));
        $this->assertFalse($vendor->can('unpublish', $product->fresh()));

        $product->forceFill(['status' => ProductStatus::Archived])->save();
        $this->assertFalse($vendor->can('publish', $product->fresh()));
        $this->assertFalse($vendor->can('unpublish', $product->fresh()));
    }

    public function test_authorization_and_incomplete_owner_gets_validation_not_forbidden(): void
    {
        Storage::fake('public');
        $owner = $this->createVendorUser();
        $other = $this->createVendorUser();
        $customer = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($owner);
        $product = app(ProductService::class)->createSimpleDraft($owner->vendor->store, [
            'type' => 'simple',
            'sku' => 'AUTH-1',
            'price' => '10',
            'quantity' => 1,
            'translations' => [
                'en' => ['name' => 'Auth'],
                'ar' => ['name' => 'تفويض'],
            ],
        ]);

        $this->assertTrue($owner->can('publish', $product));
        $this->assertFalse($other->can('publish', $product));
        $this->assertFalse($customer->can('publish', $product));
        $this->assertFalse($admin->can('publish', $product));

        $this->actingAs($owner)
            ->post(route('vendor.products.publish', $product))
            ->assertSessionHasErrors();

        $this->actingAs($other)
            ->post(route('vendor.products.publish', $product))
            ->assertForbidden();

        auth()->logout();
        $this->post(route('vendor.products.publish', $product))->assertRedirect('/login');
        $this->assertTrue(Route::has('vendor.products.publish'));
        $this->assertTrue(Route::has('vendor.products.unpublish'));
    }

    public function test_published_mutation_guards_rollback_name_and_last_image(): void
    {
        ['vendor' => $vendor, 'product' => $product] = $this->makePublishableSimple([
            'quantity' => 3,
        ]);
        $publication = app(ProductPublicationService::class);
        $publication->publish($product->fresh());
        $product = $product->fresh();

        $path = $product->images()->firstOrFail()->path;
        Storage::disk('public')->assertExists($path);

        try {
            app(ProductService::class)->updateSimpleDraft($product, [
                'type' => 'simple',
                'category_id' => $product->category_id,
                'brand_id' => $product->brand_id,
                'currency_code' => $product->currency_code,
                'sku' => $product->defaultVariant->sku,
                'price' => '1000',
                'quantity' => 3,
                'translations' => [
                    'en' => ['name' => 'Still English'],
                    'ar' => ['name' => ''],
                ],
            ]);
            $this->fail('Removing Arabic name from published product should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('translations', $exception->errors());
        }

        $this->assertTrue($product->fresh()->translations()->where('locale', 'ar')->whereNotNull('name')->exists());

        $second = app(ProductImageService::class)->upload($product->fresh(), $this->makeProductImageUpload('png', 400, 400, 'b.png'));
        $images = $product->fresh()->images()->orderBy('id')->get();
        $this->assertCount(2, $images);

        app(ProductImageService::class)->remove($product->fresh(), $images->first());
        $this->assertCount(1, $product->fresh()->images);

        $last = $product->fresh()->images()->firstOrFail();
        $lastPath = $last->path;

        try {
            app(ProductImageService::class)->remove($product->fresh(), $last);
            $this->fail('Removing last image from published product should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('image', $exception->errors());
        }

        $this->assertCount(1, $product->fresh()->images);
        Storage::disk('public')->assertExists($lastPath);
        $this->assertSame(ProductStatus::Published, $product->fresh()->status);

        // Economics remain editable.
        app(ProductService::class)->updateSimpleDraft($product->fresh(), [
            'type' => 'simple',
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'currency_code' => $product->currency_code,
            'sku' => $product->defaultVariant->sku,
            'price' => '2500',
            'quantity' => 9,
            'translations' => [
                'ar' => ['name' => 'منتج منشور'],
                'en' => ['name' => 'Publishable Product'],
            ],
        ]);
        $this->assertSame(2500, $product->fresh()->defaultVariant->price_amount_minor);
        $this->assertSame(9, $product->fresh()->defaultVariant->quantity);
    }

    public function test_unpublished_incomplete_edits_allowed_and_external_deactivation_keeps_status(): void
    {
        ['product' => $product, 'category' => $category] = $this->makePublishableSimple();
        $publication = app(ProductPublicationService::class);
        $publication->publish($product->fresh());
        $publication->unpublish($product->fresh());

        app(ProductService::class)->updateSimpleDraft($product->fresh(), [
            'type' => 'simple',
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'currency_code' => $product->currency_code,
            'sku' => $product->defaultVariant->sku,
            'price' => '1000',
            'quantity' => 1,
            'translations' => [
                'en' => ['name' => 'Incomplete OK'],
                'ar' => ['name' => ''],
            ],
        ]);

        $this->assertSame(ProductStatus::Unpublished, $product->fresh()->status);
        $this->assertFalse($product->fresh()->translations()->where('locale', 'ar')->exists());

        $product->fresh()->translations()->updateOrCreate(['locale' => 'ar'], ['name' => 'عربي']);
        $publication->publish($product->fresh());

        $category->forceFill(['is_active' => false])->save();
        $this->assertSame(ProductStatus::Published, $product->fresh()->status);

        $result = app(ProductReadinessService::class)->evaluate($product->fresh());
        $this->assertFalse($result->isPublishable());
        $this->assertTrue(collect($result->visibilityIssues)->pluck('code')->contains('inactive_category'));
    }

    public function test_publish_rechecks_stale_status_after_lock(): void
    {
        ['product' => $product] = $this->makePublishableSimple();

        Product::query()->whereKey($product->id)->update(['status' => ProductStatus::Suspended->value]);

        try {
            app(ProductPublicationService::class)->publish($product);
            $this->fail('Stale suspended status should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
    }

    public function test_keeping_inactive_category_on_unrelated_edit_is_allowed(): void
    {
        ['product' => $product, 'category' => $category] = $this->makePublishableSimple();
        app(ProductPublicationService::class)->publish($product->fresh());
        $category->forceFill(['is_active' => false])->save();

        app(ProductService::class)->updateSimpleDraft($product->fresh(), [
            'type' => 'simple',
            'category_id' => $category->id,
            'brand_id' => $product->brand_id,
            'currency_code' => $product->currency_code,
            'sku' => $product->defaultVariant->sku,
            'price' => '1800',
            'quantity' => 1,
            'translations' => [
                'ar' => ['name' => 'منتج منشور'],
                'en' => ['name' => 'Publishable Product'],
            ],
        ]);

        $this->assertSame(1800, $product->fresh()->defaultVariant->price_amount_minor);
        $this->assertSame($category->id, $product->fresh()->category_id);
        $this->assertSame(ProductStatus::Published, $product->fresh()->status);
    }

    public function test_variable_combination_key_and_incomplete_matrix_codes(): void
    {
        ['product' => $product, 'attrs' => $attrs] = $this->makePublishableVariable();
        $variant = $product->variants()->firstOrFail();
        $expected = CombinationKey::forVariable([
            $attrs['color']->id => $attrs['red']->id,
            $attrs['size']->id => $attrs['small']->id,
        ]);
        $this->assertSame($expected, $variant->combination_key);

        $variant->forceFill(['combination_key' => 'a1:v1'])->save();
        $codes = collect(app(ProductReadinessService::class)->evaluate($product->fresh())->integrityIssues)->pluck('code');
        $this->assertTrue($codes->contains('invalid_combination_key'));
    }

    public function test_stale_product_argument_does_not_treat_old_dependency_as_unchanged(): void
    {
        ['product' => $product, 'category' => $categoryA] = $this->makePublishableSimple();
        $categoryB = Category::factory()->create(['is_active' => true]);
        $brandB = Brand::factory()->create(['is_active' => true]);

        $stale = $product->fresh();
        $this->assertSame($categoryA->id, $stale->category_id);

        Product::query()->whereKey($product->id)->update([
            'category_id' => $categoryB->id,
            'brand_id' => $brandB->id,
            'currency_code' => 'USD',
        ]);
        Currency::query()->where('code', 'USD')->update(['is_active' => true]);
        $categoryA->forceFill(['is_active' => false])->save();
        Brand::query()->whereKey($product->brand_id)->update(['is_active' => false]);
        Currency::query()->where('code', 'SYP')->update(['is_active' => false]);

        try {
            app(ProductService::class)->updateSimpleDraft($stale, [
                'type' => 'simple',
                'category_id' => $categoryA->id,
                'brand_id' => $product->brand_id,
                'currency_code' => 'SYP',
                'sku' => $stale->defaultVariant->sku,
                'price' => '1000',
                'quantity' => 1,
                'translations' => [
                    'ar' => ['name' => 'منتج منشور'],
                    'en' => ['name' => 'Publishable Product'],
                ],
            ]);
            $this->fail('Stale inactive dependencies must not be treated as unchanged.');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $this->assertTrue(
                isset($errors['category_id']) || isset($errors['brand_id']) || isset($errors['currency_code']),
                'Expected at least one dependency rejection against the locked product.'
            );
        }

        $fresh = $product->fresh();
        $this->assertSame($categoryB->id, $fresh->category_id);
        $this->assertSame($brandB->id, $fresh->brand_id);
        $this->assertSame('USD', $fresh->currency_code);
    }

    public function test_http_update_keeps_exact_inactive_currency_and_rejects_switch(): void
    {
        ['vendor' => $vendor, 'product' => $product] = $this->makePublishableSimple();
        Currency::query()->where('code', 'USD')->update(['is_active' => true]);
        Currency::query()->where('code', 'SYP')->update(['is_active' => false]);

        $payload = [
            'type' => 'simple',
            'slug' => $product->slug,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'currency_code' => 'SYP',
            'sku' => $product->defaultVariant->sku,
            'price' => '1500',
            'compare_at_price' => null,
            'quantity' => 2,
            'translations' => [
                'ar' => ['name' => 'منتج منشور', 'short_description' => null, 'description' => null],
                'en' => ['name' => 'Publishable Product', 'short_description' => null, 'description' => null],
            ],
        ];

        $this->actingAs($vendor)
            ->put(route('vendor.products.update', $product), $payload)
            ->assertRedirect(route('vendor.products.edit', $product));

        $this->assertSame('SYP', $product->fresh()->currency_code);
        $this->assertSame(1500, $product->fresh()->defaultVariant->price_amount_minor);

        Currency::query()->where('code', 'USD')->update(['is_active' => false]);

        $this->actingAs($vendor)
            ->put(route('vendor.products.update', $product->fresh()), array_replace($payload, [
                'currency_code' => 'USD',
                'price' => '1600',
            ]))
            ->assertSessionHasErrors('currency_code');

        $this->assertSame('SYP', $product->fresh()->currency_code);
    }

    public function test_dual_admin_vendor_staff_cannot_publish_or_unpublish(): void
    {
        ['vendor' => $vendor, 'product' => $product] = $this->makePublishableSimple();
        app(ProductPublicationService::class)->publish($product->fresh());

        $vendor->assignRole(Role::ADMIN);
        $vendor = $vendor->fresh(['roles', 'vendor.store']);

        $this->assertTrue($vendor->isStaff());
        $this->assertTrue($vendor->hasRole(Role::VENDOR));
        $this->assertTrue($vendor->vendor->store->is($product->store));

        $this->assertFalse($vendor->can('publish', $product->fresh()));
        $this->assertFalse($vendor->can('unpublish', $product->fresh()));

        $this->actingAs($vendor)
            ->post(route('vendor.products.publish', $product))
            ->assertForbidden();

        $this->actingAs($vendor)
            ->post(route('vendor.products.unpublish', $product))
            ->assertForbidden();

        $this->assertSame(ProductStatus::Published, $product->fresh()->status);
    }

    public function test_category_not_leaf_is_visibility_issue_and_blocks_publish(): void
    {
        ['product' => $product, 'category' => $category] = $this->makePublishableSimple();
        Category::factory()->create(['parent_id' => $category->id, 'is_active' => true]);

        $result = app(ProductReadinessService::class)->evaluate($product->fresh());
        $this->assertTrue(collect($result->publicationIssues())->pluck('code')->contains('category_not_leaf'));
        $this->assertTrue(collect($result->visibilityIssues)->pluck('code')->contains('category_not_leaf'));
        $this->assertFalse($result->isPublishable());
    }

    public function test_inactive_attribute_value_blocks_first_publication(): void
    {
        ['product' => $product, 'attrs' => $attrs] = $this->makePublishableVariable();
        $attrs['red']->forceFill(['is_active' => false])->save();

        try {
            app(ProductPublicationService::class)->publish($product->fresh());
            $this->fail('Inactive attribute value should block first publication.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attributes', $exception->errors());
        }

        $codes = collect(app(ProductReadinessService::class)->evaluate($product->fresh())->publicationIssues())
            ->pluck('code');
        $this->assertTrue($codes->contains('inactive_first_publication_value'));
    }

    public function test_soft_deleted_selected_value_and_contaminated_simple_are_not_publishable(): void
    {
        ['product' => $variable] = $this->makePublishableVariable();
        $selected = $variable->productAttributes()->firstOrFail()->selectedValues()->firstOrFail();
        $selected->delete();

        $codes = collect(app(ProductReadinessService::class)->evaluate($variable->fresh())->integrityIssues)->pluck('code');
        $this->assertTrue($codes->contains('soft_deleted_variant_attribute_value')
            || $codes->contains('missing_assignment_values')
            || $codes->contains('orphan_variant_attribute_link')
            || $codes->contains('incomplete_variant_combination'));
        $this->assertFalse(app(ProductReadinessService::class)->evaluate($variable->fresh())->isPublishable());

        ['product' => $simple] = $this->makePublishableSimple();
        $attribute = Attribute::factory()->create(['is_active' => true]);
        ProductAttribute::query()->create([
            'product_id' => $simple->id,
            'attribute_id' => $attribute->id,
            'position' => 0,
        ]);

        $codes = collect(app(ProductReadinessService::class)->evaluate($simple->fresh())->integrityIssues)->pluck('code');
        $this->assertTrue($codes->contains('invalid_simple_attributes'));
        $this->assertFalse(app(ProductReadinessService::class)->evaluate($simple->fresh())->isPublishable());
    }

    public function test_published_variable_rejects_broken_combination_commit_and_allows_economics(): void
    {
        ['vendor' => $vendor, 'product' => $product, 'attrs' => $attrs, 'category' => $category] = $this->makePublishableVariable();
        app(ProductPublicationService::class)->publish($product->fresh());
        $product = $product->fresh();

        try {
            app(ProductService::class)->updateVariableDraft($product, [
                'type' => 'variable',
                'category_id' => $category->id,
                'currency_code' => $product->currency_code,
                'translations' => [
                    'ar' => ['name' => 'قميص متغير'],
                    'en' => ['name' => 'Variable Shirt'],
                ],
                'attributes' => [
                    ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id, $attrs['blue']->id]],
                    ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id, $attrs['medium']->id]],
                ],
                'variants' => [
                    [
                        'value_ids' => [$attrs['red']->id],
                        'sku' => 'BROKEN-RS',
                        'price' => '100',
                        'quantity' => 1,
                        'is_default' => true,
                    ],
                ],
            ]);
            $this->fail('Broken persisted combination must not commit on published variable product.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        $this->assertSame(ProductStatus::Published, $product->fresh()->status);
        $this->assertCount(2, $product->fresh()->variants);

        app(ProductService::class)->updateVariableDraft($product->fresh(), [
            'type' => 'variable',
            'category_id' => $category->id,
            'currency_code' => $product->currency_code,
            'translations' => [
                'ar' => ['name' => 'قميص متغير'],
                'en' => ['name' => 'Variable Shirt'],
            ],
            'attributes' => [
                ['attribute_id' => $attrs['color']->id, 'value_ids' => [$attrs['red']->id, $attrs['blue']->id]],
                ['attribute_id' => $attrs['size']->id, 'value_ids' => [$attrs['small']->id, $attrs['medium']->id]],
            ],
            'variants' => [
                [
                    'value_ids' => [$attrs['red']->id, $attrs['small']->id],
                    'sku' => 'VAR-RS-ECO',
                    'price' => '333',
                    'quantity' => 7,
                    'is_default' => true,
                ],
                [
                    'value_ids' => [$attrs['blue']->id, $attrs['medium']->id],
                    'sku' => 'VAR-BM-ECO',
                    'price' => '444',
                    'quantity' => 2,
                    'is_default' => false,
                ],
            ],
        ]);

        $updated = $product->fresh(['variants']);
        $this->assertSame(333, $updated->variants->firstWhere('sku', 'VAR-RS-ECO')->price_amount_minor);
        $this->assertSame(7, $updated->variants->firstWhere('sku', 'VAR-RS-ECO')->quantity);

        $vendor->refresh();
        $this->assertTrue($vendor->can('update', $updated));
    }

    public function test_non_normalized_sku_is_readiness_integrity_issue(): void
    {
        ['product' => $product] = $this->makePublishableSimple();
        $product->defaultVariant->forceFill(['sku' => ' lower '])->save();

        $codes = collect(app(ProductReadinessService::class)->evaluate($product->fresh())->integrityIssues)->pluck('code');
        $this->assertTrue($codes->contains('invalid_sku'));
    }

    public function test_s7a_translation_parity_and_no_new_migrations_or_packages(): void
    {
        $en = json_decode(File::get(lang_path('en.json')), true);
        $ar = json_decode(File::get(lang_path('ar.json')), true);
        $this->assertEqualsCanonicalizing(array_keys($en), array_keys($ar));

        foreach ([
            'Product published.',
            'Product unpublished.',
            'Add an Arabic product name before publishing.',
            'Add an English product name before publishing.',
            'Upload at least one product image before publishing.',
            'Your store must be active before publishing.',
            'A simple product cannot have attribute assignments or variant attribute links.',
            'Each assigned attribute must have at least one selected value.',
            'A live variant links to a removed attribute value selection.',
        ] as $key) {
            $this->assertArrayHasKey($key, $en);
            $this->assertArrayHasKey($key, $ar);
        }

        $migrations = collect(glob(database_path('migrations/*.php')))
            ->map(fn (string $path): string => basename($path));
        $this->assertFalse($migrations->contains(fn (string $name): bool => str_contains($name, 's7a')));
        $this->assertFalse(Route::has('admin.products.publish'));
        $this->assertFalse(Route::has('vendor.products.convert'));
    }
}
