<?php

namespace App\Cart;

/**
 * Query-free cart line presentation row (C1-C).
 *
 * @phpstan-type MoneyPayload array{currency_code: string, exponent: int, amount_minor: string}
 * @phpstan-type SelectionLabel array{attribute_code: string, attribute_name: string, value_code: string, value_name: string}
 */
final class CartViewLine
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_ADJUSTED = 'adjusted';

    public const STATUS_UNAVAILABLE = 'unavailable';

    /**
     * @param  MoneyPayload|null  $unitPrice
     * @param  MoneyPayload|null  $lineTotal
     * @param  list<SelectionLabel>  $selection
     */
    public function __construct(
        public readonly int $variantId,
        public readonly int $productId,
        public readonly string $productSlug,
        public readonly string $productName,
        public readonly string $storeName,
        public readonly string $storeSlug,
        public readonly ?string $imageUrl,
        public readonly ?string $imageAlt,
        public readonly ?int $imageWidth,
        public readonly ?int $imageHeight,
        public readonly string $currencyCode,
        public readonly int $currencyExponent,
        public readonly ?array $unitPrice,
        public readonly ?array $lineTotal,
        public readonly array $selection,
        public readonly int $requestedQuantity,
        public readonly int $effectiveQuantity,
        public readonly string $status,
        public readonly ?string $unavailableReason,
    ) {}

    public function contributesToTotals(): bool
    {
        return $this->status !== self::STATUS_UNAVAILABLE && $this->effectiveQuantity > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'variant_id' => $this->variantId,
            'product_id' => $this->productId,
            'product_slug' => $this->productSlug,
            'product_name' => $this->productName,
            'store_name' => $this->storeName,
            'store_slug' => $this->storeSlug,
            'image_url' => $this->imageUrl,
            'image_alt' => $this->imageAlt,
            'image_width' => $this->imageWidth,
            'image_height' => $this->imageHeight,
            'currency_code' => $this->currencyCode,
            'currency_exponent' => $this->currencyExponent,
            'unit_price' => $this->unitPrice,
            'line_total' => $this->lineTotal,
            'selection' => $this->selection,
            'requested_quantity' => $this->requestedQuantity,
            'effective_quantity' => $this->effectiveQuantity,
            'status' => $this->status,
            'unavailable_reason' => $this->unavailableReason,
        ];
    }
}
