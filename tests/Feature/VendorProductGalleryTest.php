<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\ProductImageService;
use App\Services\ProductService;
use App\Support\VendorProductGalleryState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VendorProductGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_s5a_docker_mysql_gate_was_accepted(): void
    {
        $path = base_path('docs/s5a-gate-acceptance.md');

        $this->assertFileExists($path);
        $this->assertStringContainsString('Accepted', File::get($path));
    }

    public function test_edit_page_renders_ordered_gallery_bootstrap_with_primary_image(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $product = $this->seedGalleryProduct($vendor, 3);

        $orderedIds = $product->fresh()->images()->pluck('id')->all();
        $primaryId = $product->fresh()->primary_image_id;

        $this->actingAs($vendor)
            ->get(route('vendor.products.edit', $product))
            ->assertOk()
            ->assertSee(__('Product images'), false)
            ->assertSee('vendorProductGallery(', false)
            ->assertSee('primaryImageId', false);

        foreach ($orderedIds as $index => $id) {
            $this->assertSame($index, $product->fresh()->images()->whereKey($id)->value('position'));
        }

        $bootstrap = VendorProductGalleryState::bootstrap($product->fresh(), true);
        $this->assertSame($primaryId, $bootstrap['primaryImageId']);
        $this->assertSame($orderedIds, collect($bootstrap['images'])->pluck('id')->all());
        $this->assertTrue(collect($bootstrap['images'])->firstWhere('id', $primaryId)['isPrimary']);
    }

    public function test_create_page_shows_save_first_guidance(): void
    {
        $vendor = $this->createVendorUser();

        $this->actingAs($vendor)
            ->get(route('vendor.products.create'))
            ->assertOk()
            ->assertSee(__('Product images'), false)
            ->assertSee(__('Save the product draft first, then upload images from Edit Product.'), false)
            ->assertDontSee('vendorProductGallery(', false)
            ->assertDontSee(__('Choose files'), false);
    }

    public function test_editable_vendor_sees_gallery_controls_on_edit(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $product = $this->seedGalleryProduct($vendor, 2);

        $this->actingAs($vendor)
            ->get(route('vendor.products.edit', $product))
            ->assertOk()
            ->assertSee(__('Choose files'), false)
            ->assertSee(__('Set as primary'), false)
            ->assertSee(__('Remove image'), false)
            ->assertSee(__('Save image order'), false)
            ->assertSee(__('Edit alt text'), false);
    }

    public function test_suspended_and_archived_product_gallery_is_read_only(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();

        foreach ([ProductStatus::Suspended, ProductStatus::Archived] as $status) {
            $product = $this->seedGalleryProduct($vendor, 2);

            if ($status === ProductStatus::Archived) {
                app(ProductService::class)->archive($product->fresh());
            } else {
                $product->forceFill(['status' => ProductStatus::Suspended])->save();
            }

            $this->actingAs($vendor)
                ->get(route('vendor.products.edit', $product->fresh()))
                ->assertOk()
                ->assertSee(__('Product images'), false)
                ->assertSee('vendorProductGallery(', false)
                ->assertSee('canEdit', false)
                ->assertDontSee('openFilePicker()', false)
                ->assertDontSee('type="file"', false);

            $bootstrap = VendorProductGalleryState::bootstrap($product->fresh(), false);
            $this->assertFalse($bootstrap['canEdit']);
            $this->assertSame([], (array) $bootstrap['routes']);
            foreach ($bootstrap['images'] as $image) {
                $this->assertArrayNotHasKey('routes', $image);
            }
        }
    }

    public function test_read_only_bootstrap_contains_no_mutation_urls(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $product = $this->seedGalleryProduct($vendor, 2);
        $product->forceFill(['status' => ProductStatus::Suspended])->save();

        $bootstrap = VendorProductGalleryState::bootstrap($product->fresh(), false);
        $encoded = json_encode($bootstrap, JSON_THROW_ON_ERROR);

        $this->assertFalse($bootstrap['canEdit']);
        $this->assertSame([], (array) $bootstrap['routes']);
        $this->assertStringNotContainsString('images/reorder', $encoded);
        $this->assertStringNotContainsString('/primary', $encoded);
        $this->assertStringNotContainsString('/translations', $encoded);
        $this->assertNotEmpty($bootstrap['images']);
        $this->assertArrayHasKey('url', $bootstrap['images'][0]);
        $this->assertArrayHasKey('fallbackAlt', $bootstrap['images'][0]);
    }

    public function test_foreign_vendor_forbidden_on_edit(): void
    {
        $owner = $this->createVendorUser();
        $other = $this->createVendorUser();
        $product = Product::factory()->create(['store_id' => $owner->vendor->store->id]);

        $this->actingAs($other)
            ->get(route('vendor.products.edit', $product))
            ->assertForbidden();
    }

    public function test_gallery_bootstrap_contains_no_absolute_filesystem_paths(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $product = $this->seedGalleryProduct($vendor, 2);

        $bootstrap = VendorProductGalleryState::bootstrap($product->fresh(), true);
        $encoded = json_encode($bootstrap, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('storage/app', $encoded);
        $this->assertStringNotContainsString('storage\\app', $encoded);
        $this->assertStringNotContainsString('C:\\', $encoded);
        $this->assertStringNotContainsString('C:/', $encoded);

        foreach ($bootstrap['images'] as $image) {
            $this->assertStringStartsWith('/storage/', $image['url']);
            $this->assertDoesNotMatchRegularExpression('#^[A-Za-z]:\\\\#', $image['url']);
        }

        $this->actingAs($vendor)
            ->get(route('vendor.products.edit', $product))
            ->assertOk()
            ->assertDontSee('storage/app', false)
            ->assertDontSee('storage\\app', false);
    }

    public function test_json_upload_returns_authoritative_gallery(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);

        $response = $this->actingAs($vendor)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('vendor.products.images.store', $product), [
                'image' => $this->makeProductImageUpload('jpeg', 400, 400, 'gallery.jpg'),
            ])
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'primary_image_id',
                'image_id',
                'gallery' => [
                    'primary_image_id',
                    'images' => [
                        ['id', 'url', 'position', 'isPrimary'],
                    ],
                ],
            ]);

        $payload = $response->json();
        $this->assertSame($payload['primary_image_id'], $payload['gallery']['primary_image_id']);
        $this->assertCount(1, $payload['gallery']['images']);
        $this->assertTrue($payload['gallery']['images'][0]['isPrimary']);
    }

    public function test_json_reorder_returns_authoritative_order(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $product = $this->seedGalleryProduct($vendor, 3);
        $reordered = array_reverse($product->fresh()->images()->pluck('id')->all());

        $response = $this->actingAs($vendor)
            ->withHeaders(['Accept' => 'application/json'])
            ->put(route('vendor.products.images.reorder', $product), [
                'image_ids' => $reordered,
            ])
            ->assertOk()
            ->assertJsonPath('gallery.primary_image_id', $product->fresh()->primary_image_id);

        $this->assertSame(
            $reordered,
            collect($response->json('gallery.images'))->pluck('id')->all(),
        );
        $this->assertSame($reordered, $product->fresh()->images()->pluck('id')->all());
    }

    public function test_json_primary_action_updates_gallery(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $product = $this->seedGalleryProduct($vendor, 2);
        $nextPrimary = $product->fresh()->images()->where('id', '!=', $product->primary_image_id)->firstOrFail();

        $response = $this->actingAs($vendor)
            ->withHeaders(['Accept' => 'application/json'])
            ->put(route('vendor.products.images.primary', ['product' => $product, 'product_image' => $nextPrimary]))
            ->assertOk()
            ->assertJsonPath('gallery.primary_image_id', $nextPrimary->id);

        $primaryFlags = collect($response->json('gallery.images'))->pluck('isPrimary', 'id');
        $this->assertTrue($primaryFlags[$nextPrimary->id]);
        $this->assertSame($nextPrimary->id, $product->fresh()->primary_image_id);
    }

    public function test_json_alt_update_and_blank_removal(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $product = $this->seedGalleryProduct($vendor, 1);
        $image = $product->fresh()->images()->firstOrFail();

        $this->actingAs($vendor)
            ->withHeaders(['Accept' => 'application/json'])
            ->put(route('vendor.products.images.translations', ['product' => $product, 'product_image' => $image]), [
                'translations' => [
                    'en' => ['alt_text' => 'Hero shot'],
                    'ar' => ['alt_text' => 'لقطة'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('gallery.images.0.altEn', 'Hero shot')
            ->assertJsonPath('gallery.images.0.altAr', 'لقطة');

        $cleared = $this->actingAs($vendor)
            ->withHeaders(['Accept' => 'application/json'])
            ->put(route('vendor.products.images.translations', ['product' => $product, 'product_image' => $image->fresh()]), [
                'translations' => [
                    'en' => ['alt_text' => ''],
                    'ar' => ['alt_text' => ''],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('gallery.images.0.altEn', '')
            ->assertJsonPath('gallery.images.0.altAr', '');

        $image->refresh()->load(['translations', 'product.translations']);
        $this->assertSame($product->fresh()->name('en'), $image->altText('en'));
        $this->assertSame($product->fresh()->name('en'), $cleared->json('gallery.images.0.fallbackAlt'));
    }

    public function test_json_image_removal_and_primary_reassignment(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $product = $this->seedGalleryProduct($vendor, 2);
        $primary = $product->fresh()->images()->whereKey($product->primary_image_id)->firstOrFail();
        $remaining = $product->fresh()->images()->whereKeyNot($primary->id)->firstOrFail();

        $response = $this->actingAs($vendor)
            ->withHeaders(['Accept' => 'application/json'])
            ->delete(route('vendor.products.images.destroy', ['product' => $product, 'product_image' => $primary]))
            ->assertOk()
            ->assertJsonPath('gallery.primary_image_id', $remaining->id)
            ->assertJsonCount(1, 'gallery.images');

        $this->assertFalse(ProductImage::query()->whereKey($primary->id)->exists());
        $this->assertTrue(collect($response->json('gallery.images'))->first()['isPrimary']);
        $this->assertSame($remaining->id, $product->fresh()->primary_image_id);
    }

    public function test_multipart_redirect_fallback_still_works(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);

        $this->actingAs($vendor)
            ->post(route('vendor.products.images.store', $product), [
                'image' => $this->makeProductImageUpload(),
            ])
            ->assertRedirect(route('vendor.products.edit', $product))
            ->assertSessionHas('status', __('Product image uploaded.'));

        $this->assertSame(1, $product->fresh()->images()->count());
    }

    public function test_primary_thumbnail_on_vendor_index_when_image_exists(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $product = $this->seedGalleryProduct($vendor, 1);
        $url = $product->fresh()->primaryImage->url();

        $this->actingAs($vendor)
            ->get(route('vendor.products'))
            ->assertOk()
            ->assertSee($url, false)
            ->assertSee(VendorProductGalleryState::thumbnailAlt($product->fresh()), false);
    }

    public function test_no_image_placeholder_on_index(): void
    {
        $vendor = $this->createVendorUser();
        Product::factory()->create(['store_id' => $vendor->vendor->store->id]);

        $this->actingAs($vendor)
            ->get(route('vendor.products'))
            ->assertOk()
            ->assertSee(__('No product image'), false);
    }

    public function test_presenter_does_not_lazy_load_products_or_translations_per_image(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);

        $products = collect();
        for ($i = 0; $i < 3; $i++) {
            $products->push($this->seedGalleryProduct($vendor, 4));
        }

        $loaded = Product::query()
            ->whereIn('id', $products->pluck('id'))
            ->with(['images.translations', 'primaryImage.translations', 'translations'])
            ->get();

        DB::flushQueryLog();
        DB::enableQueryLog();

        foreach ($loaded as $product) {
            $state = VendorProductGalleryState::bootstrap($product, true);
            $this->assertCount(4, $state['images']);
            $this->assertNotSame('', $state['images'][0]['fallbackAlt']);
            VendorProductGalleryState::thumbnailAlt($product);
        }

        $queries = collect(DB::getQueryLog())->pluck('query')->map(fn (string $sql): string => strtolower($sql));
        DB::disableQueryLog();

        $this->assertSame(0, $this->countTableQueries($queries, 'products'));
        $this->assertSame(0, $this->countTableQueries($queries, 'product_translations'));
        $this->assertSame(0, $this->countTableQueries($queries, 'product_images'));
        $this->assertSame(0, $this->countTableQueries($queries, 'product_image_translations'));
    }

    public function test_index_eager_loads_without_per_product_or_per_image_n_plus_one(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);
        $service = app(ProductImageService::class);

        $makeSet = function (int $count) use ($vendor, $service): void {
            for ($i = 0; $i < $count; $i++) {
                $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);
                $service->upload($product, $this->makeProductImageUpload('jpeg', 400, 400, 'n1-'.$count.'-'.$i.'.jpg'));
            }
        };

        $makeSet(4);
        $small = $this->countIndexQueries($vendor);
        $makeSet(8);
        $large = $this->countIndexQueries($vendor);

        foreach (['product_images', 'product_image_translations', 'product_translations'] as $table) {
            $this->assertSame($small[$table], $large[$table], $table.' query count grew with more products');
            $this->assertLessThanOrEqual(2, $large[$table], $table.' should stay bounded');
        }

        $this->assertLessThanOrEqual($small['products'] + 2, $large['products']);
    }

    public function test_broken_image_placeholder_markup_exists(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $product = $this->seedGalleryProduct($vendor, 1);

        $this->actingAs($vendor)
            ->get(route('vendor.products.edit', $product))
            ->assertOk()
            ->assertSee('data-broken-image-fallback', false)
            ->assertSee('markImageFailed(image)', false)
            ->assertSee(__('Image unavailable'), false);

        $this->actingAs($vendor)
            ->get(route('vendor.products'))
            ->assertOk()
            ->assertSee('data-broken-image-fallback', false)
            ->assertSee('x-on:error="failed = true"', false);
    }

    public function test_queue_and_interaction_labels_and_alt_input_ids_are_present(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $product = $this->seedGalleryProduct($vendor, 1);
        $imageId = $product->fresh()->images()->value('id');

        $this->actingAs($vendor)
            ->get(route('vendor.products.edit', $product))
            ->assertOk()
            ->assertSee(__('Queued'), false)
            ->assertSee(__('Uploading…'), false)
            ->assertSee(__('Completed'), false)
            ->assertSee(__('Dismiss'), false)
            ->assertSee(__('Discard order changes'), false)
            ->assertSee(__('Discard alt text'), false)
            ->assertSee("alt-ar-' + image.id", false)
            ->assertSee("alt-en-' + image.id", false)
            ->assertSee('discardOrderChanges()', false)
            ->assertSee('data-gallery-order-dirty', false)
            ->assertSee('data-gallery-status', false)
            ->assertSee('queueStateLabel(item)', false)
            ->assertSee('onHandleDragStart', false);

        $this->assertNotNull($imageId);
    }

    public function test_s5b_adds_no_migrations_packages_or_staff_image_routes(): void
    {
        $migrations = collect(glob(database_path('migrations/*.php')))
            ->map(fn (string $path): string => basename($path));

        $this->assertFalse($migrations->contains(fn (string $name): bool => str_contains($name, 's5b')));
        $this->assertSame(1, $migrations->filter(fn (string $name): bool => str_contains($name, 'product_image'))->count());
        $this->assertArrayNotHasKey('vue', json_decode(File::get(base_path('package.json')), true)['dependencies'] ?? []);
        $this->assertFalse(Route::has('admin.products.images.store'));
        $this->assertFalse(Route::has('vendor.products.convert'));
    }

    public function test_product_images_translation_parity(): void
    {
        $en = json_decode(File::get(lang_path('en.json')), true);
        $ar = json_decode(File::get(lang_path('ar.json')), true);

        $this->assertIsArray($en);
        $this->assertIsArray($ar);
        $this->assertEqualsCanonicalizing(array_keys($en), array_keys($ar));

        foreach ([
            'Product images',
            'Completed',
            'Dismiss',
            'Discard order changes',
            'Discard alt text',
            'Image unavailable',
            'Save or discard the gallery order first.',
            'Save or discard unsaved alt text first.',
            'Drag to reorder',
        ] as $key) {
            $this->assertArrayHasKey($key, $en);
            $this->assertArrayHasKey($key, $ar);
            $this->assertNotSame('', $ar[$key]);
        }
    }

    private function seedGalleryProduct(User $vendor, int $imageCount): Product
    {
        $this->actingAs($vendor);
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);
        $service = app(ProductImageService::class);

        for ($i = 0; $i < $imageCount; $i++) {
            $service->upload(
                $product->fresh(),
                $this->makeProductImageUpload('jpeg', 400, 400, 'gallery-'.$i.'.jpg'),
            );
        }

        return $product->fresh(['images', 'primaryImage']);
    }

    /**
     * @return array{products: int, product_images: int, product_image_translations: int, product_translations: int}
     */
    private function countIndexQueries(User $vendor): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($vendor)
            ->get(route('vendor.products'))
            ->assertOk();

        $queries = collect(DB::getQueryLog())->pluck('query')->map(fn (string $sql): string => strtolower($sql));
        DB::disableQueryLog();

        return [
            'products' => $this->countTableQueries($queries, 'products'),
            'product_images' => $this->countTableQueries($queries, 'product_images'),
            'product_image_translations' => $this->countTableQueries($queries, 'product_image_translations'),
            'product_translations' => $this->countTableQueries($queries, 'product_translations'),
        ];
    }

    /**
     * @param  Collection<int, string>  $queries
     */
    private function countTableQueries($queries, string $table): int
    {
        return $queries->filter(function (string $sql) use ($table): bool {
            if ($table === 'product_images' && str_contains($sql, 'product_image_translations')) {
                return false;
            }

            return str_contains($sql, '`'.$table.'`')
                || str_contains($sql, '"'.$table.'"');
        })->count();
    }
}
