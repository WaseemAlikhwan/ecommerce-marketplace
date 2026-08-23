<?php

namespace App\Storefront\Presentation;

use App\Support\ModelFreePayload;

/**
 * Query-free Product detail presentation state.
 *
 * @phpstan-type MoneyPayload array{currency_code: string, exponent: int, amount_minor: string}
 */
final class ProductDetailState
{
    /**
     * @param  list<array{slug: string, name: string, url: string}>  $breadcrumbs
     * @param  list<array{id: int, url: string, alt: string, width: ?int, height: ?int, position: int, is_primary: bool}>  $gallery
     * @param  list<array{code: string, name: string, values: list<array{code: string, name: string}>}>  $attributes
     * @param  list<array<string, mixed>>  $variants
     * @param  array<string, mixed>|null  $defaultVariant
     * @param  MoneyPayload  $selectedPrice
     * @param  MoneyPayload|null  $selectedCompareAt
     * @param  list<array<string, mixed>>  $related
     * @param  array{name: string, slug: string, url: string, description: ?string, logo_url: ?string, initials: string}  $store
     */
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $url,
        public readonly string $type,
        public readonly string $name,
        public readonly ?string $shortDescription,
        public readonly ?string $description,
        public readonly array $breadcrumbs,
        public readonly array $store,
        public readonly ?string $brandName,
        public readonly ?string $brandSlug,
        public readonly array $gallery,
        public readonly array $attributes,
        public readonly array $variants,
        public readonly ?array $defaultVariant,
        public readonly string $currencyCode,
        public readonly int $currencyExponent,
        public readonly array $selectedPrice,
        public readonly string $selectedPriceLabel,
        public readonly string $minPriceLabel,
        public readonly string $maxPriceLabel,
        public readonly string $priceRangeLabel,
        public readonly ?array $selectedCompareAt,
        public readonly ?string $selectedCompareAtLabel,
        public readonly bool $inStock,
        public readonly array $related,
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
            'type' => $this->type,
            'name' => $this->name,
            'short_description' => $this->shortDescription,
            'description' => $this->description,
            'breadcrumbs' => $this->breadcrumbs,
            'store' => $this->store,
            'brand' => $this->brandName === null ? null : [
                'name' => $this->brandName,
                'slug' => $this->brandSlug,
            ],
            'gallery' => $this->gallery,
            'attributes' => $this->attributes,
            'variants' => $this->variants,
            'default_variant' => $this->defaultVariant,
            'currency_code' => $this->currencyCode,
            'currency_exponent' => $this->currencyExponent,
            'selected_price' => $this->selectedPrice,
            'selected_price_label' => $this->selectedPriceLabel,
            'price_min_label' => $this->minPriceLabel,
            'price_max_label' => $this->maxPriceLabel,
            'price_range_label' => $this->priceRangeLabel,
            'selected_compare_at' => $this->selectedCompareAt,
            'selected_compare_at_label' => $this->selectedCompareAtLabel,
            'in_stock' => $this->inStock,
            'related' => $this->related,
        ];

        ModelFreePayload::assert($payload, 'ProductDetailState::toArray()');

        return $payload;
    }
}
