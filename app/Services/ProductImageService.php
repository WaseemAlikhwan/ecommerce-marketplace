<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductImageTranslation;
use App\Support\Locale;
use App\Support\ProductImageProcessor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductImageService
{
    public function __construct(
        private readonly ProductImageProcessor $processor,
        private readonly ProductReadinessService $readiness,
    ) {}

    public function upload(Product $product, UploadedFile $file): ProductImage
    {
        $this->assertVendorEditable($product);

        $normalized = $this->processor->normalize($file);
        $path = $this->uniquePath($product->id, $normalized->extension);
        $stored = false;

        try {
            return DB::transaction(function () use ($product, $normalized, $path, &$stored): ProductImage {
                /** @var Product $product */
                $product = Product::query()->lockForUpdate()->findOrFail($product->id);
                $this->assertVendorEditable($product);

                $images = $this->lockedImages($product);
                $this->assertGalleryInvariant($product, $images);

                if ($images->count() >= ProductImage::MAX_PER_PRODUCT) {
                    throw ValidationException::withMessages([
                        'image' => __('A product may have at most 8 images.'),
                    ]);
                }

                $written = Storage::disk('public')->put($path, $normalized->bytes);
                if ($written !== true) {
                    $this->cleanupStoredPath($path, 'storage write returned false');
                    throw new \RuntimeException('Failed to store the product image.');
                }
                $stored = true;

                $image = ProductImage::query()->create([
                    'product_id' => $product->id,
                    'store_id' => $product->store_id,
                    'path' => $path,
                    'mime_type' => $normalized->mimeType,
                    'size_bytes' => $normalized->sizeBytes,
                    'width' => $normalized->width,
                    'height' => $normalized->height,
                    'position' => $images->count() === 0 ? 0 : ((int) $images->max('position') + 1),
                ]);

                if ($product->primary_image_id === null) {
                    $product->forceFill(['primary_image_id' => $image->id])->save();
                }

                return $image->refresh();
            });
        } catch (Throwable $exception) {
            if ($stored) {
                $this->cleanupStoredPath($path, 'post-insert failure', $exception);
            }

            throw $exception;
        }
    }

    /**
     * @param  list<int>  $imageIds
     */
    public function reorder(Product $product, array $imageIds): Product
    {
        $this->assertVendorEditable($product);

        return DB::transaction(function () use ($product, $imageIds): Product {
            /** @var Product $product */
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $this->assertVendorEditable($product);

            $images = $this->lockedImages($product);
            $this->assertGalleryInvariant($product, $images);

            $normalizedIds = array_values(array_map('intval', $imageIds));
            if (count($normalizedIds) !== count(array_unique($normalizedIds))) {
                throw ValidationException::withMessages([
                    'image_ids' => __('Image order must include each image once.'),
                ]);
            }

            $current = $images->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
            $submitted = collect($normalizedIds)->sort()->values();
            if ($current->all() !== $submitted->all()) {
                throw ValidationException::withMessages([
                    'image_ids' => __('Image order must match the current gallery.'),
                ]);
            }

            $this->assignPositions($product, $normalizedIds);

            return $product->refresh()->load(['images', 'primaryImage']);
        });
    }

    public function setPrimary(Product $product, ProductImage $image): Product
    {
        $this->assertVendorEditable($product);

        return DB::transaction(function () use ($product, $image): Product {
            /** @var Product $product */
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $this->assertVendorEditable($product);

            $images = $this->lockedImages($product);
            $this->assertGalleryInvariant($product, $images);

            $target = $images->firstWhere('id', $image->id);
            if ($target === null || ! $target->belongsToProduct($product)) {
                throw ValidationException::withMessages([
                    'image' => __('The selected image does not belong to this product.'),
                ]);
            }

            $product->forceFill(['primary_image_id' => $target->id])->save();

            return $product->refresh()->load(['images', 'primaryImage']);
        });
    }

    /**
     * @param  array<string, array{alt_text?: string|null}>  $translations
     */
    public function updateAltTexts(Product $product, ProductImage $image, array $translations): ProductImage
    {
        $this->assertVendorEditable($product);

        return DB::transaction(function () use ($product, $image, $translations): ProductImage {
            /** @var Product $product */
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $this->assertVendorEditable($product);

            $images = $this->lockedImages($product);
            $this->assertGalleryInvariant($product, $images);

            $target = $images->firstWhere('id', $image->id);
            if ($target === null || ! $target->belongsToProduct($product)) {
                throw ValidationException::withMessages([
                    'image' => __('The selected image does not belong to this product.'),
                ]);
            }

            foreach (Locale::SUPPORTED as $locale) {
                if (! array_key_exists($locale, $translations) || ! is_array($translations[$locale]) || ! array_key_exists('alt_text', $translations[$locale])) {
                    continue;
                }

                $value = trim((string) ($translations[$locale]['alt_text'] ?? ''));
                $existing = ProductImageTranslation::query()
                    ->where('product_image_id', $target->id)
                    ->where('locale', $locale)
                    ->first();

                if ($value === '') {
                    $existing?->delete();

                    continue;
                }

                ProductImageTranslation::query()->updateOrCreate(
                    ['product_image_id' => $target->id, 'locale' => $locale],
                    ['alt_text' => $value],
                );
            }

            return $target->refresh()->load(['translations', 'product.translations']);
        });
    }

    public function remove(Product $product, ProductImage $image): Product
    {
        $this->assertVendorEditable($product);

        $path = null;

        $product = DB::transaction(function () use ($product, $image, &$path): Product {
            /** @var Product $product */
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $this->assertVendorEditable($product);

            $images = $this->lockedImages($product);
            $this->assertGalleryInvariant($product, $images);

            $target = $images->firstWhere('id', $image->id);
            if ($target === null || ! $target->belongsToProduct($product)) {
                throw ValidationException::withMessages([
                    'image' => __('The selected image does not belong to this product.'),
                ]);
            }

            $path = $target->path;
            $remainingIds = $images
                ->reject(fn (ProductImage $row): bool => $row->id === $target->id)
                ->sortBy('position')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if ((int) $product->primary_image_id === (int) $target->id) {
                $product->forceFill([
                    'primary_image_id' => $remainingIds[0] ?? null,
                ])->save();
            }

            $target->delete();
            $this->assignPositions($product, $remainingIds);

            $fresh = $product->refresh()->load([
                'images',
                'primaryImage',
                'translations',
                'variants',
                'defaultVariant',
                'currency',
                'category',
                'brand',
                'store.vendor',
                'productAttributes.attribute',
                'productAttributes.selectedValues.attributeValue',
                'variants.attributeValueLinks.productAttributeValue',
            ]);

            $this->readiness->assertIntegrityForPublished($fresh);

            return $fresh;
        });

        if (is_string($path) && $path !== '') {
            $this->deleteCommittedImageFile($path, $product->id);
        }

        return $product;
    }

    private function cleanupStoredPath(string $path, string $context, ?Throwable $previous = null): void
    {
        try {
            $deleted = Storage::disk('public')->delete($path);
            $remains = Storage::disk('public')->exists($path);
        } catch (Throwable $cleanupException) {
            Log::warning('Failed to clean up product image file.', [
                'path' => $path,
                'context' => $context,
                'cleanup_exception' => $cleanupException->getMessage(),
                'exception' => $previous?->getMessage(),
            ]);

            return;
        }

        if ($remains) {
            Log::warning('Failed to clean up product image file.', [
                'path' => $path,
                'context' => $context,
                'delete_returned' => $deleted,
                'exception' => $previous?->getMessage(),
            ]);
        }
    }

    private function deleteCommittedImageFile(string $path, int $productId): void
    {
        try {
            $deleted = Storage::disk('public')->delete($path);
            if ($deleted === true) {
                return;
            }

            if ($this->pathExistsIgnoringErrors($path, true)) {
                Log::warning('Orphan product image file after row deletion.', [
                    'path' => $path,
                    'product_id' => $productId,
                    'delete_returned' => $deleted,
                ]);
            }
        } catch (Throwable $exception) {
            if ($this->pathExistsIgnoringErrors($path, true)) {
                Log::warning('Orphan product image file after row deletion.', [
                    'path' => $path,
                    'product_id' => $productId,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function pathExistsIgnoringErrors(string $path, bool $assumeRemainsOnError): bool
    {
        try {
            return Storage::disk('public')->exists($path);
        } catch (Throwable) {
            return $assumeRemainsOnError;
        }
    }

    /**
     * @param  Collection<int, ProductImage>  $images
     */
    private function assertGalleryInvariant(Product $product, $images): void
    {
        if ($images->isEmpty()) {
            if ($product->primary_image_id !== null) {
                throw ValidationException::withMessages([
                    'primary_image_id' => __('Product primary image is invalid.'),
                ]);
            }

            return;
        }

        if ($product->primary_image_id === null || ! $images->contains('id', $product->primary_image_id)) {
            throw ValidationException::withMessages([
                'primary_image_id' => __('Product primary image is invalid.'),
            ]);
        }
    }

    private function assertVendorEditable(Product $product): void
    {
        $user = auth()->user();
        if ($user === null || ! $user->can('update', $product)) {
            throw new AuthorizationException;
        }
    }

    /**
     * @return Collection<int, ProductImage>
     */
    private function lockedImages(Product $product)
    {
        return ProductImage::query()
            ->where('product_id', $product->id)
            ->where('store_id', $product->store_id)
            ->orderBy('position')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  list<int>  $imageIdsInOrder
     */
    private function assignPositions(Product $product, array $imageIdsInOrder): void
    {
        if ($imageIdsInOrder === []) {
            return;
        }

        $offset = ProductImage::query()
            ->where('product_id', $product->id)
            ->max('position');
        $offset = is_numeric($offset) ? ((int) $offset + 1000) : 1000;

        ProductImage::query()
            ->where('product_id', $product->id)
            ->update(['position' => DB::raw('position + '.$offset)]);

        foreach ($imageIdsInOrder as $index => $imageId) {
            ProductImage::query()
                ->where('product_id', $product->id)
                ->where('id', $imageId)
                ->update(['position' => $index]);
        }
    }

    private function uniquePath(int $productId, string $extension): string
    {
        do {
            $path = 'products/'.$productId.'/'.strtolower((string) Str::ulid()).'.'.$extension;
        } while (Storage::disk('public')->exists($path) || ProductImage::query()->where('path', $path)->exists());

        return $path;
    }
}
