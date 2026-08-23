<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use App\Services\ProductService;
use App\Support\NormalizedProductImage;
use App\Support\ProductImageProcessor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\TestCase;

class ProductImageDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_and_constraints(): void
    {
        $this->assertTrue(Schema::hasTable('product_images'));
        $this->assertTrue(Schema::hasTable('product_image_translations'));
        $this->assertTrue(Schema::hasColumn('products', 'primary_image_id'));
        $this->assertFalse(Schema::hasColumn('product_images', 'variant_id'));
        $this->assertFalse(Schema::hasColumn('product_images', 'alt_text'));
        $this->assertFalse(Schema::hasColumn('product_images', 'deleted_at'));
        $this->assertFalse(Schema::hasColumn('product_images', 'is_primary'));

        foreach (['product_id', 'store_id', 'path', 'mime_type', 'size_bytes', 'width', 'height', 'position'] as $column) {
            $this->assertTrue(Schema::hasColumn('product_images', $column));
        }
    }

    public function test_composite_store_ownership_and_unique_path_and_position(): void
    {
        $owner = $this->createVendorUser();
        $other = $this->createVendorUser();
        $product = Product::factory()->create(['store_id' => $owner->vendor->store->id]);

        $this->expectException(QueryException::class);
        DB::table('product_images')->insert([
            'product_id' => $product->id,
            'store_id' => $other->vendor->store->id,
            'path' => 'products/'.$product->id.'/mismatch.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1000,
            'width' => 400,
            'height' => 400,
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cross_product_primary_fk_is_rejected(): void
    {
        $owner = $this->createVendorUser();
        $first = Product::factory()->create(['store_id' => $owner->vendor->store->id]);
        $second = Product::factory()->create(['store_id' => $owner->vendor->store->id]);
        $image = ProductImage::factory()->create([
            'product_id' => $second->id,
            'store_id' => $second->store_id,
            'path' => 'products/'.$second->id.'/a.jpg',
            'position' => 0,
        ]);

        $this->expectException(QueryException::class);
        DB::table('products')->where('id', $first->id)->update(['primary_image_id' => $image->id]);
    }

    public function test_unique_path_and_position_constraints(): void
    {
        $vendor = $this->createVendorUser();
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);
        ProductImage::factory()->create([
            'product_id' => $product->id,
            'store_id' => $product->store_id,
            'path' => 'products/'.$product->id.'/same.jpg',
            'position' => 0,
        ]);

        try {
            ProductImage::factory()->create([
                'product_id' => $product->id,
                'store_id' => $product->store_id,
                'path' => 'products/'.$product->id.'/same.jpg',
                'position' => 1,
            ]);
            $this->fail('Duplicate path should fail.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->expectException(QueryException::class);
        ProductImage::factory()->create([
            'product_id' => $product->id,
            'store_id' => $product->store_id,
            'path' => 'products/'.$product->id.'/other.jpg',
            'position' => 0,
        ]);
    }

    public function test_processor_normalizes_jpeg_png_and_webp(): void
    {
        Storage::fake('public');
        $processor = app(ProductImageProcessor::class);

        foreach (['jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'] as $format => $mime) {
            $result = $processor->normalize($this->makeProductImageUpload($format, 420, 410, 'ignore.'.$format));
            $this->assertSame($mime, $result->mimeType);
            $this->assertSame(420, $result->width);
            $this->assertSame(410, $result->height);
            $this->assertGreaterThan(0, $result->sizeBytes);
            $this->assertSame(ProductImageProcessor::MIME_EXTENSIONS[$mime], $result->extension);
            $this->assertNotSame('', $result->bytes);
        }
    }

    public function test_processor_rejects_svg_gif_bmp_spoof_and_dimension_limits(): void
    {
        $processor = app(ProductImageProcessor::class);

        $svg = tmpfile();
        $svgPath = stream_get_meta_data($svg)['uri'];
        fwrite($svg, '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400"></svg>');
        rewind($svg);

        foreach ([
            $this->makeProductImageUpload('gif', 400, 400, 'x.gif'),
            $this->makeProductImageUpload('bmp', 400, 400, 'x.bmp'),
            new UploadedFile($svgPath, 'x.svg', 'image/svg+xml', null, true),
            $this->makeProductImageUpload('gif', 400, 400, 'photo.jpg', false),
            $this->makeProductImageUpload('jpeg', 399, 400, 'tiny.jpg'),
            $this->makeProductImageUpload('jpeg', 6001, 400, 'wide.jpg'),
        ] as $file) {
            try {
                $processor->normalize($file);
                $this->fail('Expected rejection for '.$file->getClientOriginalName());
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_processor_rejects_pixel_count_and_input_size(): void
    {
        $processor = app(ProductImageProcessor::class);

        try {
            $processor->normalize($this->makeProductImageUpload('jpeg', 4001, 4000, 'huge.jpg'));
            $this->fail('16MP+ should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('image', $exception->errors());
        }

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'s5a-oversize.jpg';
        file_put_contents($path, str_repeat('A', ProductImageProcessor::MAX_BYTES + 1));
        $oversize = new UploadedFile($path, 'oversize.jpg', 'image/jpeg', null, true);

        try {
            $processor->normalize($oversize);
            $this->fail('Oversize input should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('image', $exception->errors());
        }
    }

    public function test_processor_rejects_normalized_output_over_limit(): void
    {
        $processor = app(ProductImageProcessor::class);

        try {
            $processor->normalize($this->makeProductImageUpload('png', 1800, 1800, 'noise.png', true));
            $this->fail('Noisy PNG should exceed normalized size.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('image', $exception->errors());
        }
    }

    public function test_upload_sets_primary_once_and_respects_max_and_random_path(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);
        $service = app(ProductImageService::class);

        $first = $service->upload($product, $this->makeProductImageUpload('jpeg', 400, 400, 'payload.php.jpg'));
        $second = $service->upload($product->fresh(), $this->makeProductImageUpload('png', 400, 400, 'second.png'));

        $this->assertSame($first->id, $product->fresh()->primary_image_id);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(0, $first->position);
        $this->assertSame(1, $second->position);
        $this->assertSame('image/jpeg', $first->mime_type);
        $this->assertSame(400, $first->width);
        $this->assertStringStartsWith('products/'.$product->id.'/', $first->path);
        $this->assertStringEndsWith('.jpg', $first->path);
        $this->assertStringNotContainsString('payload', $first->path);
        $this->assertTrue(Storage::disk('public')->exists($first->path));

        for ($i = 0; $i < 6; $i++) {
            $service->upload($product->fresh(), $this->makeProductImageUpload('jpeg', 400, 400, 'n'.$i.'.jpg'));
        }

        $this->assertSame(8, $product->fresh()->images()->count());

        $this->expectException(ValidationException::class);
        $service->upload($product->fresh(), $this->makeProductImageUpload('jpeg', 400, 400, 'ninth.jpg'));
    }

    public function test_reorder_set_primary_delete_and_alt_fallback(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);
        $product->translations()->updateOrCreate(['locale' => 'en'], ['name' => 'English Shirt']);
        $product->translations()->updateOrCreate(['locale' => 'ar'], ['name' => 'قميص عربي']);
        $service = app(ProductImageService::class);

        $a = $service->upload($product->fresh(), $this->makeProductImageUpload('jpeg', 400, 400, 'a.jpg'));
        $b = $service->upload($product->fresh(), $this->makeProductImageUpload('jpeg', 400, 400, 'b.jpg'));
        $c = $service->upload($product->fresh(), $this->makeProductImageUpload('jpeg', 400, 400, 'c.jpg'));

        try {
            $service->reorder($product->fresh(), [$c->id, $a->id]);
            $this->fail('Incomplete reorder should fail.');
        } catch (ValidationException) {
            $this->assertSame([$a->id, $b->id, $c->id], $product->fresh()->images()->orderBy('position')->pluck('id')->all());
        }

        $service->reorder($product->fresh(), [$c->id, $a->id, $b->id]);
        $this->assertSame([$c->id, $a->id, $b->id], $product->fresh()->images()->pluck('id')->all());
        $this->assertSame($a->id, $product->fresh()->primary_image_id);

        $service->setPrimary($product->fresh(), $c->fresh());
        $this->assertSame($c->id, $product->fresh()->primary_image_id);
        $this->assertSame(0, $c->fresh()->position);

        $nonPrimary = $b->fresh();
        $service->remove($product->fresh(), $nonPrimary);
        $this->assertFalse(ProductImage::query()->whereKey($nonPrimary->id)->exists());
        $this->assertFalse(Storage::disk('public')->exists($nonPrimary->path));
        $this->assertSame([0, 1], $product->fresh()->images()->pluck('position')->all());

        $service->remove($product->fresh(), $c->fresh());
        $this->assertSame($a->id, $product->fresh()->primary_image_id);

        $updated = $service->updateAltTexts($product->fresh(), $a->fresh(), [
            'en' => ['alt_text' => 'Front view'],
            'ar' => ['alt_text' => ''],
        ]);
        $this->assertSame('Front view', $updated->altText('en'));
        $this->assertSame('Front view', $updated->altText('ar'));

        $service->updateAltTexts($product->fresh(), $a->fresh(), [
            'en' => ['alt_text' => ''],
            'ar' => ['alt_text' => 'واجهة'],
        ]);
        $a->refresh()->load(['translations', 'product.translations']);
        $this->assertFalse($a->translations->contains('locale', 'en'));
        $this->assertSame('واجهة', $a->altText('ar'));
        $this->assertSame('واجهة', $a->altText('en'));

        $service->updateAltTexts($product->fresh(), $a->fresh(), [
            'en' => ['alt_text' => ''],
            'ar' => ['alt_text' => ''],
        ]);
        $a->refresh()->load(['translations', 'product.translations']);
        $this->assertSame('English Shirt', $a->altText('en'));
        $this->assertSame('قميص عربي', $a->altText('ar'));

        $service->remove($product->fresh(), $a->fresh());
        $this->assertNull($product->fresh()->primary_image_id);
        $this->assertSame(0, $product->fresh()->images()->count());
    }

    public function test_stale_duplicate_reorder_is_atomic_and_failed_insert_deletes_file(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);
        $service = app(ProductImageService::class);
        $a = $service->upload($product->fresh(), $this->makeProductImageUpload());
        $b = $service->upload($product->fresh(), $this->makeProductImageUpload());

        try {
            $service->reorder($product->fresh(), [$a->id, $a->id]);
            $this->fail('Duplicate reorder should fail.');
        } catch (ValidationException) {
            $this->assertSame([$a->id, $b->id], $product->fresh()->images()->orderBy('position')->pluck('id')->all());
        }

        ProductImage::creating(function (): void {
            throw new \RuntimeException('db fail');
        });

        try {
            $service->upload($product->fresh(), $this->makeProductImageUpload('jpeg', 400, 400, 'gone.jpg'));
            $this->fail('Upload should fail.');
        } catch (\RuntimeException) {
            $this->assertCount(2, Storage::disk('public')->allFiles());
        }
    }

    public function test_archive_retains_image_rows_and_files(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);
        $image = app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());

        app(ProductService::class)->archive($product->fresh());

        $this->assertTrue(ProductImage::query()->whereKey($image->id)->exists());
        $this->assertTrue(Storage::disk('public')->exists($image->path));
        $this->assertSame($image->id, Product::withTrashed()->find($product->id)?->primary_image_id);
    }

    public function test_storage_put_false_rolls_back_without_image_or_primary(): void
    {
        $this->mockPublicDisk(function (MockInterface $mock): void {
            $mock->shouldReceive('put')->andReturn(false);
        });

        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);

        try {
            app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());
            $this->fail('Storage write failure should abort upload.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Failed to store the product image.', $exception->getMessage());
        }

        $this->assertSame(0, ProductImage::query()->where('product_id', $product->id)->count());
        $this->assertNull($product->fresh()->primary_image_id);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_failed_insert_cleanup_logs_false_delete_and_exception_without_hiding_original(): void
    {
        $logs = $this->captureLogMessages();
        $this->mockPublicDisk(function (MockInterface $mock): void {
            $mock->shouldReceive('delete')->andReturn(false);
        });

        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);

        ProductImage::creating(function (): void {
            throw new \RuntimeException('db fail');
        });

        try {
            app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());
            $this->fail('Upload should fail after a DB exception.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('db fail', $exception->getMessage());
        }

        $this->assertSame(0, ProductImage::query()->where('product_id', $product->id)->count());
        $this->assertNull($product->fresh()->primary_image_id);
        $this->assertTrue($this->logContains($logs, 'Failed to clean up product image file.', [
            'context' => 'post-insert failure',
        ]));

        $logs = $this->captureLogMessages();
        $this->mockPublicDisk(function (MockInterface $mock): void {
            $mock->shouldReceive('delete')->andThrow(new \RuntimeException('cleanup io'));
        });
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);

        try {
            app(ProductImageService::class)->upload($product, $this->makeProductImageUpload());
            $this->fail('Upload should fail after a DB exception.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('db fail', $exception->getMessage());
        }

        $this->assertTrue($this->logContains($logs, 'Failed to clean up product image file.', [
            'cleanup_exception' => 'cleanup io',
        ]));
    }

    public function test_delete_false_logs_orphan_only_when_file_remains(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);
        $service = app(ProductImageService::class);
        $image = $service->upload($product, $this->makeProductImageUpload());
        $path = $image->path;

        $logs = $this->captureLogMessages();
        $this->replacePublicDisk(function (MockInterface $mock): void {
            $mock->shouldReceive('delete')->andReturn(false);
        });

        $service->remove($product->fresh(), $image->fresh());

        $this->assertFalse(ProductImage::query()->whereKey($image->id)->exists());
        $this->assertNull($product->fresh()->primary_image_id);
        $this->assertTrue($this->logContains($logs, 'Orphan product image file after row deletion.', [
            'path' => $path,
        ]));

        Storage::fake('public');
        $remaining = $service->upload($product->fresh(), $this->makeProductImageUpload());
        $logs = $this->captureLogMessages();
        $this->replacePublicDisk(function (MockInterface $mock): void {
            $mock->shouldReceive('delete')->andReturn(false);
            $mock->shouldReceive('exists')->andReturn(false);
        });

        $service->remove($product->fresh(), $remaining->fresh());

        $this->assertFalse(ProductImage::query()->whereKey($remaining->id)->exists());
        $this->assertFalse($this->logContains($logs, 'Orphan product image file after row deletion.'));
    }

    public function test_delete_exception_logs_orphan_only_when_file_remains(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);
        $service = app(ProductImageService::class);
        $image = $service->upload($product, $this->makeProductImageUpload());

        $logs = $this->captureLogMessages();
        $this->replacePublicDisk(function (MockInterface $mock): void {
            $mock->shouldReceive('delete')->andThrow(new \RuntimeException('disk down'));
        });

        $service->remove($product->fresh(), $image->fresh());

        $this->assertFalse(ProductImage::query()->whereKey($image->id)->exists());
        $this->assertTrue($this->logContains($logs, 'Orphan product image file after row deletion.', [
            'exception' => 'disk down',
        ]));

        Storage::fake('public');
        $remaining = $service->upload($product->fresh(), $this->makeProductImageUpload());
        $logs = $this->captureLogMessages();
        $this->replacePublicDisk(function (MockInterface $mock): void {
            $mock->shouldReceive('delete')->andThrow(new \RuntimeException('disk down'));
            $mock->shouldReceive('exists')->andReturn(false);
        });

        $service->remove($product->fresh(), $remaining->fresh());

        $this->assertFalse(ProductImage::query()->whereKey($remaining->id)->exists());
        $this->assertFalse($this->logContains($logs, 'Orphan product image file after row deletion.'));
    }

    public function test_unauthorized_service_upload_is_rejected_before_normalization(): void
    {
        $processor = new class extends ProductImageProcessor
        {
            public bool $normalized = false;

            public function normalize(UploadedFile $file): NormalizedProductImage
            {
                $this->normalized = true;

                return parent::normalize($file);
            }
        };
        $this->app->instance(ProductImageProcessor::class, $processor);

        $owner = $this->createVendorUser();
        $other = $this->createVendorUser();
        $product = Product::factory()->create(['store_id' => $owner->vendor->store->id]);
        $file = $this->makeProductImageUpload();

        $this->actingAs($other);

        try {
            app(ProductImageService::class)->upload($product, $file);
            $this->fail('Unauthorized upload should fail.');
        } catch (AuthorizationException) {
            $this->assertFalse($processor->normalized);
        }
    }

    public function test_empty_alt_payload_does_not_clear_translations(): void
    {
        Storage::fake('public');
        $vendor = $this->createVendorUser();
        $this->actingAs($vendor);
        $product = Product::factory()->create(['store_id' => $vendor->vendor->store->id]);
        $service = app(ProductImageService::class);
        $image = $service->upload($product, $this->makeProductImageUpload());

        $service->updateAltTexts($product->fresh(), $image->fresh(), [
            'en' => ['alt_text' => 'Front'],
            'ar' => ['alt_text' => 'واجهة'],
        ]);

        $service->updateAltTexts($product->fresh(), $image->fresh(), []);
        $image->refresh()->load('translations');
        $this->assertSame('Front', $image->altText('en'));
        $this->assertSame('واجهة', $image->altText('ar'));

        $service->updateAltTexts($product->fresh(), $image->fresh(), [
            'en' => ['alt_text' => ''],
        ]);
        $image->refresh()->load('translations');
        $this->assertFalse($image->translations->contains('locale', 'en'));
        $this->assertSame('واجهة', $image->altText('ar'));
    }

    public function test_processor_applies_exif_orientation_and_strips_metadata(): void
    {
        $processor = app(ProductImageProcessor::class);

        $oriented = $processor->normalize($this->makeJpegWithExifOrientation(6, 400, 500));
        $this->assertSame(500, $oriented->width);
        $this->assertSame(400, $oriented->height);
        $this->assertStringNotContainsString("Exif\x00\x00", $oriented->bytes);

        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'s5a-normalized.jpg';
        file_put_contents($tmp, $oriented->bytes);
        $exif = @exif_read_data($tmp);
        $this->assertTrue($exif === false || ! isset($exif['Orientation']));

        $gd = imagecreatefromstring($oriented->bytes);
        $this->assertInstanceOf(\GdImage::class, $gd);
        $this->assertMostlyColor($this->rgbAt($gd, 480, 20), 255, 0, 0);
        imagedestroy($gd);

        $mirrored = $processor->normalize($this->makeJpegWithExifOrientation(2, 400, 400));
        $this->assertSame(400, $mirrored->width);
        $this->assertSame(400, $mirrored->height);
        $flip = imagecreatefromstring($mirrored->bytes);
        $this->assertMostlyColor($this->rgbAt($flip, 20, 20), 0, 220, 0);
        $this->assertMostlyColor($this->rgbAt($flip, 380, 20), 255, 0, 0);
        imagedestroy($flip);

        foreach ([5, 7] as $orientation) {
            $result = $processor->normalize($this->makeJpegWithExifOrientation($orientation, 400, 500));
            $this->assertSame(500, $result->width, 'orientation '.$orientation);
            $this->assertSame(400, $result->height, 'orientation '.$orientation);
            $this->assertStringNotContainsString("Exif\x00\x00", $result->bytes);
        }
    }

    /**
     * @return \ArrayObject<int, MessageLogged>
     */
    private function captureLogMessages(): \ArrayObject
    {
        $logs = new \ArrayObject;
        Log::listen(function (MessageLogged $event) use ($logs): void {
            $logs[] = $event;
        });

        return $logs;
    }

    /**
     * @param  \ArrayObject<int, MessageLogged>|list<MessageLogged>  $logs
     * @param  array<string, mixed>  $context
     */
    private function logContains(\ArrayObject|array $logs, string $message, array $context = []): bool
    {
        foreach ($logs as $event) {
            if ($event->message !== $message) {
                continue;
            }

            $matches = true;
            foreach ($context as $key => $value) {
                if (($event->context[$key] ?? null) !== $value) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  callable(MockInterface, FilesystemAdapter): void  $configure
     */
    private function mockPublicDisk(callable $configure): void
    {
        Storage::fake('public');
        $this->replacePublicDisk($configure);
    }

    /**
     * @param  callable(MockInterface, FilesystemAdapter): void  $configure
     */
    private function replacePublicDisk(callable $configure): void
    {
        $real = Storage::disk('public');
        $mock = \Mockery::mock($real)->makePartial();
        $configure($mock, $real);
        Storage::set('public', $mock);
    }

    private function makeJpegWithExifOrientation(int $orientation, int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 240, 240, 240));
        imagefilledrectangle($image, 0, 0, 79, 79, imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, $width - 80, 0, $width - 1, 79, imagecolorallocate($image, 0, 220, 0));
        imagefilledrectangle($image, 0, $height - 80, 79, $height - 1, imagecolorallocate($image, 0, 0, 220));
        imagefilledrectangle($image, $width - 80, $height - 80, $width - 1, $height - 1, imagecolorallocate($image, 220, 220, 0));

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'s5a-exif-'.Str::ulid().'.jpg';
        imagejpeg($image, $path, 95);
        imagedestroy($image);

        $jpeg = file_get_contents($path);
        $this->assertIsString($jpeg);
        $this->assertSame("\xFF\xD8", substr($jpeg, 0, 2));
        file_put_contents($path, "\xFF\xD8".$this->exifApp1($orientation).substr($jpeg, 2));

        $exif = @exif_read_data($path);
        $this->assertIsArray($exif);
        $this->assertSame($orientation, (int) ($exif['Orientation'] ?? 0));

        return new UploadedFile($path, 'oriented.jpg', 'image/jpeg', null, true);
    }

    private function exifApp1(int $orientation): string
    {
        $tiff = "II*\x00\x08\x00\x00\x00";
        $ifd = "\x01\x00";
        $ifd .= "\x12\x01\x03\x00\x01\x00\x00\x00".pack('v', $orientation)."\x00\x00";
        $ifd .= "\x00\x00\x00\x00";
        $payload = "Exif\x00\x00".$tiff.$ifd;
        $length = strlen($payload) + 2;

        return "\xFF\xE1".pack('n', $length).$payload;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function rgbAt(\GdImage $image, int $x, int $y): array
    {
        $color = imagecolorat($image, $x, $y);
        $this->assertNotFalse($color);

        return [($color >> 16) & 255, ($color >> 8) & 255, $color & 255];
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function assertMostlyColor(array $rgb, int $r, int $g, int $b): void
    {
        $this->assertLessThan(40, abs($rgb[0] - $r));
        $this->assertLessThan(40, abs($rgb[1] - $g));
        $this->assertLessThan(40, abs($rgb[2] - $b));
    }
}
