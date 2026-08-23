<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\ProductImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VendorProductImageHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_customer_admin_and_other_vendor_cannot_mutate_images(): void
    {
        Storage::fake('public');
        $owner = $this->createVendorUser();
        $other = $this->createVendorUser();
        $customer = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['store_id' => $owner->vendor->store->id]);
        $file = $this->makeProductImageUpload();

        $this->post(route('vendor.products.images.store', $product), ['image' => $file])
            ->assertRedirect('/login');

        $this->actingAs($customer)
            ->post(route('vendor.products.images.store', $product), ['image' => $file])
            ->assertForbidden();
        $this->actingAs($admin)
            ->post(route('vendor.products.images.store', $product), ['image' => $file])
            ->assertForbidden();
        $this->actingAs($other)
            ->post(route('vendor.products.images.store', $product), ['image' => $file])
            ->assertForbidden();
    }

    public function test_vendor_upload_reorder_primary_translations_and_delete(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);

        $this->actingAs($vendor)
            ->post(route('vendor.products.images.store', $product), [
                'image' => $this->makeProductImageUpload('jpeg', 400, 400, 'one.jpg'),
            ])
            ->assertRedirect(route('vendor.products.edit', $product));

        $first = $product->fresh()->images()->first();
        $this->assertNotNull($first);
        $this->assertSame($first->id, $product->fresh()->primary_image_id);

        $this->actingAs($vendor)
            ->post(route('vendor.products.images.store', $product), [
                'image' => $this->makeProductImageUpload('png', 400, 400, 'two.png'),
            ]);
        $this->actingAs($vendor)
            ->post(route('vendor.products.images.store', $product), [
                'image' => $this->makeProductImageUpload('webp', 400, 400, 'three.webp'),
            ]);

        $ids = $product->fresh()->images()->pluck('id')->all();
        $this->actingAs($vendor)
            ->put(route('vendor.products.images.reorder', $product), [
                'image_ids' => array_reverse($ids),
            ])
            ->assertRedirect(route('vendor.products.edit', $product));
        $this->assertSame(array_reverse($ids), $product->fresh()->images()->pluck('id')->all());
        $this->assertSame($first->id, $product->fresh()->primary_image_id);

        $newPrimary = $product->fresh()->images()->where('id', '!=', $first->id)->firstOrFail();
        $this->actingAs($vendor)
            ->put(route('vendor.products.images.primary', ['product' => $product, 'product_image' => $newPrimary]))
            ->assertRedirect(route('vendor.products.edit', $product));
        $this->assertSame($newPrimary->id, $product->fresh()->primary_image_id);

        $this->actingAs($vendor)
            ->put(route('vendor.products.images.translations', ['product' => $product, 'product_image' => $newPrimary]), [
                'translations' => [
                    'en' => ['alt_text' => 'Hero'],
                    'ar' => ['alt_text' => 'رئيسية'],
                ],
            ])
            ->assertRedirect(route('vendor.products.edit', $product));
        $this->assertSame('Hero', $newPrimary->fresh()->load('translations')->altText('en'));

        $this->actingAs($vendor)
            ->delete(route('vendor.products.images.destroy', ['product' => $product, 'product_image' => $first]))
            ->assertRedirect(route('vendor.products.edit', $product));
        $this->assertFalse(ProductImage::query()->whereKey($first->id)->exists());
    }

    public function test_json_upload_and_nested_image_from_another_product_is_rejected(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);
        $other = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);

        $this->actingAs($vendor)
            ->post(route('vendor.products.images.store', $product), [
                'image' => $this->makeProductImageUpload(),
            ]);
        $foreign = app(ProductImageService::class)->upload(
            $other,
            $this->makeProductImageUpload('jpeg', 400, 400, 'foreign.jpg'),
        );

        $this->actingAs($vendor)
            ->put(route('vendor.products.images.primary', ['product' => $product, 'product_image' => $foreign]))
            ->assertNotFound();

        $this->actingAs($vendor)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('vendor.products.images.store', $product), [
                'image' => $this->makeProductImageUpload('jpeg', 400, 400, 'json.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('status', __('Product image uploaded.'))
            ->assertJsonStructure([
                'status',
                'primary_image_id',
                'image_id',
                'gallery' => [
                    'primary_image_id',
                    'images',
                ],
            ]);
    }

    public function test_http_rejects_disallowed_formats_and_has_no_staff_or_conversion_routes(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);

        $this->actingAs($vendor)
            ->post(route('vendor.products.images.store', $product), [
                'image' => $this->makeProductImageUpload('gif', 400, 400, 'no.gif'),
            ])
            ->assertSessionHasErrors('image');

        $this->assertFalse(Route::has('admin.products.images.store'));
        $this->assertFalse(Route::has('admin.products.images.destroy'));
        $this->assertFalse(Route::has('vendor.products.destroy'));
        $this->assertFalse(Route::has('vendor.products.convert'));
        $this->assertTrue(Route::has('vendor.products.images.store'));
        $this->assertTrue(Route::has('vendor.products.archive'));
    }

    public function test_simple_product_metadata_update_is_unchanged(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);

        $this->actingAs($vendor)
            ->put(route('vendor.products.update', $product), [
                'type' => 'simple',
                'slug' => $product->slug,
                'currency_code' => 'SYP',
                'sku' => $product->defaultVariant->sku,
                'price' => '10',
                'quantity' => 2,
                'translations' => [
                    'en' => ['name' => 'Still Simple', 'short_description' => null, 'description' => null],
                    'ar' => ['name' => '', 'short_description' => null, 'description' => null],
                ],
            ])
            ->assertRedirect(route('vendor.products.edit', $product));

        $this->assertSame('Still Simple', $product->fresh()->name('en'));
        $this->assertSame(0, $product->fresh()->images()->count());
    }

    public function test_empty_translation_body_is_rejected_and_does_not_clear_alt_text(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);

        $this->actingAs($vendor)
            ->post(route('vendor.products.images.store', $product), [
                'image' => $this->makeProductImageUpload(),
            ]);

        $image = $product->fresh()->images()->firstOrFail();
        $this->actingAs($vendor)
            ->put(route('vendor.products.images.translations', ['product' => $product, 'product_image' => $image]), [
                'translations' => [
                    'en' => ['alt_text' => 'Hero'],
                    'ar' => ['alt_text' => 'رئيسية'],
                ],
            ])
            ->assertRedirect(route('vendor.products.edit', $product));

        $this->actingAs($vendor)
            ->put(route('vendor.products.images.translations', ['product' => $product, 'product_image' => $image]), [])
            ->assertSessionHasErrors('translations');

        $image->refresh()->load('translations');
        $this->assertSame('Hero', $image->altText('en'));
        $this->assertSame('رئيسية', $image->altText('ar'));
    }
}
