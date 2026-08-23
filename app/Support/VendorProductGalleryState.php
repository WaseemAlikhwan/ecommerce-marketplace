<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductImage;

final class VendorProductGalleryState
{
    /**
     * @return array<string, mixed>
     */
    public static function bootstrap(Product $product, bool $canEdit): array
    {
        self::loadGalleryRelations($product);

        return [
            'canEdit' => $canEdit,
            'maxImages' => ProductImage::MAX_PER_PRODUCT,
            'maxBytes' => ProductImageProcessor::MAX_BYTES,
            'acceptedTypes' => array_keys(ProductImageProcessor::MIME_EXTENSIONS),
            'primaryImageId' => $product->primary_image_id,
            'images' => self::images($product, $canEdit),
            'routes' => $canEdit ? self::routes($product) : new \stdClass,
            'labels' => self::labels(),
        ];
    }

    /**
     * @return array{primary_image_id: int|null, images: list<array<string, mixed>>}
     */
    public static function galleryPayload(Product $product, bool $canEdit): array
    {
        self::loadGalleryRelations($product);

        return [
            'primary_image_id' => $product->primary_image_id,
            'images' => self::images($product, $canEdit),
        ];
    }

    public static function thumbnailAlt(Product $product): string
    {
        $product->loadMissing(['primaryImage.translations', 'translations']);

        if ($product->primaryImage === null) {
            return $product->name();
        }

        return self::fallbackAlt($product, $product->primaryImage);
    }

    public static function loadGalleryRelations(Product $product): void
    {
        $product->loadMissing([
            'images.translations',
            'primaryImage.translations',
            'translations',
        ]);

        foreach ($product->images as $image) {
            $image->setRelation('product', $product);
        }

        if ($product->primaryImage !== null) {
            $product->primaryImage->setRelation('product', $product);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function images(Product $product, bool $canEdit): array
    {
        return $product->images
            ->map(fn (ProductImage $image): array => self::imageState($product, $image, $canEdit))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private static function imageState(Product $product, ProductImage $image, bool $canEdit): array
    {
        $state = [
            'id' => $image->id,
            'url' => $image->url(),
            'position' => $image->position,
            'isPrimary' => (int) $product->primary_image_id === (int) $image->id,
            'mimeType' => $image->mime_type,
            'sizeBytes' => $image->size_bytes,
            'sizeLabel' => self::formatBytes($image->size_bytes),
            'width' => $image->width,
            'height' => $image->height,
            'dimensionsLabel' => $image->width.'×'.$image->height,
            'altAr' => $image->translations->firstWhere('locale', 'ar')?->alt_text ?? '',
            'altEn' => $image->translations->firstWhere('locale', 'en')?->alt_text ?? '',
            'fallbackAlt' => self::fallbackAlt($product, $image),
        ];

        if ($canEdit) {
            $state['routes'] = [
                'primary' => route('vendor.products.images.primary', [$product, $image]),
                'translations' => route('vendor.products.images.translations', [$product, $image]),
                'destroy' => route('vendor.products.images.destroy', [$product, $image]),
            ];
        }

        return $state;
    }

    private static function fallbackAlt(Product $product, ProductImage $image): string
    {
        $locale = Locale::sanitize(app()->getLocale());
        $translations = $image->relationLoaded('translations')
            ? $image->translations
            : collect();

        $translated = $translations->firstWhere('locale', $locale)?->alt_text
            ?? $translations->firstWhere('locale', 'en')?->alt_text
            ?? $translations->firstWhere('locale', 'ar')?->alt_text;

        if (filled($translated)) {
            return (string) $translated;
        }

        return $product->name($locale);
    }

    /**
     * @return array<string, string>
     */
    private static function routes(Product $product): array
    {
        return [
            'upload' => route('vendor.products.images.store', $product),
            'reorder' => route('vendor.products.images.reorder', $product),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function labels(): array
    {
        return [
            'galleryTitle' => __('Product images'),
            'galleryDescription' => __('Upload up to 8 JPEG, PNG, or WebP images. The first image becomes primary automatically.'),
            'emptyTitle' => __('No images yet'),
            'emptyDescription' => __('Add product photos to build your gallery.'),
            'primaryBadge' => __('Primary'),
            'setPrimary' => __('Set as primary'),
            'removeImage' => __('Remove image'),
            'removeConfirm' => __('Remove this image from the product?'),
            'saveOrder' => __('Save image order'),
            'discardOrder' => __('Discard order changes'),
            'orderDirty' => __('Gallery order changed. Save to apply.'),
            'orderStale' => __('The gallery changed elsewhere. Reload to continue.'),
            'reloadGallery' => __('Refresh page to resynchronize'),
            'uploading' => __('Uploading…'),
            'uploadComplete' => __('Upload complete'),
            'uploadFailed' => __('Upload failed'),
            'queued' => __('Queued'),
            'completed' => __('Completed'),
            'dismissQueueItem' => __('Dismiss'),
            'saveAlt' => __('Save alt text'),
            'discardAlt' => __('Discard alt text'),
            'altSaved' => __('Alt text saved'),
            'altFailed' => __('Could not save alt text'),
            'altFallbackHint' => __('If both alt texts are empty, the localized product name is used.'),
            'altArabic' => __('Arabic alt text'),
            'altEnglish' => __('English alt text'),
            'remainingSlots' => __(':count of :max slots remaining'),
            'slotsFull' => __('Gallery full (8 images)'),
            'dropzoneLabel' => __('Upload product images'),
            'dropzoneHint' => __('Drag and drop or choose files. JPEG, PNG, and WebP up to 5 MiB each.'),
            'chooseFiles' => __('Choose files'),
            'moveEarlier' => __('Move earlier'),
            'moveLater' => __('Move later'),
            'editAlt' => __('Edit alt text'),
            'missingFile' => __('Image unavailable'),
            'altStatusSet' => __('Alt text set'),
            'altStatusFallback' => __('Uses product name'),
            'networkError' => __('Network error. Try again.'),
            'sessionExpired' => __('Session expired. Refresh the page and try again.'),
            'forbidden' => __('You cannot change images for this product.'),
            'notFound' => __('This image is no longer available.'),
            'serverError' => __('Something went wrong. Try again.'),
            'validationError' => __('Check the highlighted fields and try again.'),
            'invalidType' => __('Only JPEG, PNG, and WebP images are allowed.'),
            'fileTooLarge' => __('The image may not be larger than 5 MiB.'),
            'primaryUpdated' => __('Primary image updated'),
            'imageRemoved' => __('Image removed'),
            'orderSaved' => __('Image order saved'),
            'busy' => __('Working…'),
            'noScriptUpload' => __('Upload one image (no JavaScript)'),
            'saveOrderFirst' => __('Save or discard the gallery order first.'),
            'saveAltFirst' => __('Save or discard unsaved alt text first.'),
            'unsavedWarning' => __('You have unsaved gallery changes.'),
            'confirmStaleRefresh' => __('Refresh the page to resynchronize the gallery? Unsaved product details may be lost.'),
            'dragHandle' => __('Drag to reorder'),
        ];
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KiB';
        }

        return number_format($bytes / (1024 * 1024), 1).' MiB';
    }
}
