<?php

namespace App\Storefront\Presentation;

use App\Support\ModelFreePayload;

/**
 * Query-free Product card presentation state.
 *
 * @phpstan-type MoneyPayload array{currency_code: string, exponent: int, amount_minor: string}
 */
final class ProductCardState
{
    /**
     * @param  MoneyPayload  $minPrice
     * @param  MoneyPayload  $maxPrice
     * @param  MoneyPayload|null  $compareAtPrice
     */
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $url,
        public readonly string $name,
        public readonly string $type,
        public readonly string $storeName,
        public readonly string $storeSlug,
        public readonly string $storeUrl,
        public readonly ?string $imageUrl,
        public readonly string $imageAlt,
        public readonly ?int $imageWidth,
        public readonly ?int $imageHeight,
        public readonly string $currencyCode,
        public readonly int $currencyExponent,
        public readonly array $minPrice,
        public readonly array $maxPrice,
        public readonly string $priceLabel,
        public readonly ?array $compareAtPrice,
        public readonly ?string $compareAtLabel,
        public readonly bool $inStock,
        public readonly bool $isSimple,
        public readonly ?int $defaultVariantId,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'id' => $this->id,
            'slug' => $this->slug,
            'url' => $this->url,
            'name' => $this->name,
            'type' => $this->type,
            'store' => [
                'name' => $this->storeName,
                'slug' => $this->storeSlug,
                'url' => $this->storeUrl,
            ],
            'image' => [
                'url' => $this->imageUrl,
                'alt' => $this->imageAlt,
                'width' => $this->imageWidth,
                'height' => $this->imageHeight,
            ],
            'currency_code' => $this->currencyCode,
            'currency_exponent' => $this->currencyExponent,
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
            'price_label' => $this->priceLabel,
            'compare_at_price' => $this->compareAtPrice,
            'compare_at_label' => $this->compareAtLabel,
            'in_stock' => $this->inStock,
            'is_simple' => $this->isSimple,
            'default_variant_id' => $this->defaultVariantId,
        ];

        ModelFreePayload::assert($payload, 'ProductCardState::toArray()');

        return $payload;
    }
}
