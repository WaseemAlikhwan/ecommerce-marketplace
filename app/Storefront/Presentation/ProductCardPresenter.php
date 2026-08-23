<?php

namespace App\Storefront\Presentation;

use App\Enums\ProductType;
use App\Models\Product;
use App\Services\Storefront\StorefrontProductQuery;
use App\Support\Locale;
use App\Support\LocalizedText;
use App\Support\Money;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;

/**
 * Builds ProductCardState from an already-loaded Product (+ listing aggregates).
 * Performs zero database queries.
 */
final class ProductCardPresenter
{
    public function present(Product $product, ?string $locale = null): ProductCardState
    {
        $locale = Locale::sanitize($locale ?? app()->getLocale());
        $this->assertCardPayload($product);

        $name = LocalizedText::pick($product->translations, $locale, 'name', $product->slug) ?? $product->slug;
        $store = $product->store;
        $currency = $product->currency;
        $exponent = (int) $currency->exponent;
        $currencyCode = (string) $currency->code;

        $minMinor = (int) $product->getAttribute(StorefrontProductQuery::AGG_MIN_PRICE);
        $maxMinor = (int) $product->getAttribute(StorefrontProductQuery::AGG_MAX_PRICE);
        $inStock = (int) $product->getAttribute(StorefrontProductQuery::AGG_IN_STOCK) === 1;
        $isSimple = $product->type === ProductType::Simple;

        $minPrice = $this->moneyPayload($currencyCode, $exponent, $minMinor);
        $maxPrice = $this->moneyPayload($currencyCode, $exponent, $maxMinor);
        $priceLabel = $minMinor === $maxMinor
            ? $this->formatMoneyLabel($currencyCode, $exponent, $minMinor)
            : $this->formatMoneyLabel($currencyCode, $exponent, $minMinor)
                .' – '.$this->formatMoneyLabel($currencyCode, $exponent, $maxMinor);

        $compareAt = null;
        $compareAtLabel = null;
        if ($isSimple) {
            $compareMinor = $product->getAttribute(StorefrontProductQuery::AGG_COMPARE_AT);
            if ($compareMinor !== null && (int) $compareMinor > $minMinor) {
                $compareAt = $this->moneyPayload($currencyCode, $exponent, (int) $compareMinor);
                $compareAtLabel = $this->formatMoneyLabel($currencyCode, $exponent, (int) $compareMinor);
            }
        }

        $image = $product->primaryImage;
        $imageUrl = $image?->path ? Storage::disk('public')->url($image->path) : null;
        $imageAlt = $image
            ? (LocalizedText::pick($image->translations, $locale, 'alt_text') ?? $name)
            : $name;

        return new ProductCardState(
            id: (int) $product->id,
            slug: (string) $product->slug,
            url: route('storefront.product', $product->slug),
            name: $name,
            type: $product->type->value,
            storeName: (string) $store->name,
            storeSlug: (string) $store->slug,
            storeUrl: route('storefront.store', $store->slug),
            imageUrl: $imageUrl,
            imageAlt: $imageAlt,
            imageWidth: $image?->width !== null ? (int) $image->width : null,
            imageHeight: $image?->height !== null ? (int) $image->height : null,
            currencyCode: $currencyCode,
            currencyExponent: $exponent,
            minPrice: $minPrice,
            maxPrice: $maxPrice,
            priceLabel: $priceLabel,
            compareAtPrice: $compareAt,
            compareAtLabel: $compareAtLabel,
            inStock: $inStock,
            isSimple: $isSimple,
            defaultVariantId: $product->default_variant_id !== null ? (int) $product->default_variant_id : null,
        );
    }

    private function assertCardPayload(Product $product): void
    {
        foreach (['translations', 'store', 'currency'] as $relation) {
            if (! $product->relationLoaded($relation)) {
                throw new LogicException("ProductCardPresenter requires loaded relation [{$relation}].");
            }
        }

        if ($product->relationLoaded('primaryImage') === false) {
            throw new LogicException('ProductCardPresenter requires loaded relation [primaryImage].');
        }

        if ($product->primaryImage !== null && ! $product->primaryImage->relationLoaded('translations')) {
            throw new LogicException('ProductCardPresenter requires loaded relation [primaryImage.translations].');
        }

        $attributes = $product->getAttributes();
        foreach ([
            StorefrontProductQuery::AGG_MIN_PRICE,
            StorefrontProductQuery::AGG_MAX_PRICE,
            StorefrontProductQuery::AGG_IN_STOCK,
            StorefrontProductQuery::AGG_COMPARE_AT,
        ] as $attribute) {
            if (! array_key_exists($attribute, $attributes)) {
                throw new RuntimeException("ProductCardPresenter requires aggregate attribute [{$attribute}].");
            }
        }

        foreach ([StorefrontProductQuery::AGG_MIN_PRICE, StorefrontProductQuery::AGG_MAX_PRICE] as $attribute) {
            if ($attributes[$attribute] === null) {
                throw new RuntimeException("ProductCardPresenter requires non-null aggregate attribute [{$attribute}].");
            }
        }

        $inStock = $attributes[StorefrontProductQuery::AGG_IN_STOCK];
        if ($inStock === null || ! in_array((int) $inStock, [0, 1], true)) {
            throw new RuntimeException('ProductCardPresenter requires a boolean aggregate attribute ['.StorefrontProductQuery::AGG_IN_STOCK.'].');
        }
    }

    /**
     * @return array{currency_code: string, exponent: int, amount_minor: string}
     */
    private function moneyPayload(string $code, int $exponent, int $minor): array
    {
        return [
            'currency_code' => $code,
            'exponent' => $exponent,
            'amount_minor' => (string) $minor,
        ];
    }

    private function formatMoneyLabel(string $code, int $exponent, int $minor): string
    {
        return Money::formatFromMinor($minor, $exponent).' '.$code;
    }
}
