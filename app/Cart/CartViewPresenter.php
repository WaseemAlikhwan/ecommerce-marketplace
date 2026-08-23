<?php

namespace App\Cart;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CheckedInteger;
use App\Support\Locale;
use App\Support\LocalizedText;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Zero-query cart presenter. Consumes already-loaded Variant graphs only.
 */
final class CartViewPresenter
{
    /**
     * @param  Collection<int, CartLine>  $cartLines
     * @param  Collection<int, ProductVariant>  $variants  keyed by variant id
     * @param  array<int, true>  $storefrontVisibleProductIds
     */
    public function present(
        Collection $cartLines,
        Collection $variants,
        array $storefrontVisibleProductIds,
        ?string $locale = null,
    ): CartView {
        $locale = Locale::sanitize($locale ?? app()->getLocale());
        $viewLines = [];
        /** @var array<string, array{exponent: int, amount_minor: int}> $accumulators */
        $accumulators = [];

        foreach ($cartLines as $cartLine) {
            $viewLine = $this->presentLine(
                $cartLine,
                $variants->get($cartLine->variantId),
                $storefrontVisibleProductIds,
                $locale,
            );
            $viewLines[] = $viewLine;

            if (! $viewLine->contributesToTotals()) {
                continue;
            }

            $unitMinor = (int) $viewLine->unitPrice['amount_minor'];
            $lineMinor = CheckedInteger::multiply($unitMinor, $viewLine->effectiveQuantity);
            $code = $viewLine->currencyCode;

            if (! isset($accumulators[$code])) {
                $accumulators[$code] = [
                    'exponent' => $viewLine->currencyExponent,
                    'amount_minor' => 0,
                ];
            }

            $accumulators[$code]['amount_minor'] = CheckedInteger::add(
                $accumulators[$code]['amount_minor'],
                $lineMinor,
            );
        }

        ksort($accumulators);

        $subtotals = [];
        foreach ($accumulators as $code => $bucket) {
            $subtotals[] = new CartCurrencySubtotal(
                currencyCode: $code,
                currencyExponent: $bucket['exponent'],
                amountMinor: $bucket['amount_minor'],
                total: $this->moneyPayload($code, $bucket['exponent'], $bucket['amount_minor']),
            );
        }

        return new CartView($viewLines, $subtotals);
    }

    /**
     * @param  array<int, true>  $storefrontVisibleProductIds
     */
    private function presentLine(
        CartLine $cartLine,
        ?ProductVariant $variant,
        array $storefrontVisibleProductIds,
        string $locale,
    ): CartViewLine {
        if ($variant === null || $variant->trashed()) {
            return $this->unavailablePlaceholder(
                $cartLine,
                CartMergeUnavailable::MISSING,
            );
        }

        $this->assertVariantGraph($variant);

        $product = $variant->product;
        if ($product === null || $product->trashed()) {
            return $this->unavailablePlaceholder(
                $cartLine,
                CartMergeUnavailable::MISSING,
            );
        }

        // Hidden / non-storefront-visible: generic unavailable — no catalog leakage.
        if (! isset($storefrontVisibleProductIds[(int) $product->id])) {
            return $this->unavailablePlaceholder(
                $cartLine,
                CartMergeUnavailable::NOT_PURCHASABLE,
            );
        }

        $stock = (int) $variant->quantity;
        if ($stock < 1) {
            return $this->detailedLine(
                $cartLine,
                $variant,
                $product,
                $locale,
                effectiveQuantity: 0,
                status: CartViewLine::STATUS_UNAVAILABLE,
                unavailableReason: CartMergeUnavailable::OUT_OF_STOCK,
                lineTotalMinor: null,
            );
        }

        $requested = $cartLine->quantity;
        $effective = min($requested, $stock);
        $adjusted = $effective < $requested;
        $unitMinor = (int) $variant->price_amount_minor;
        $lineMinor = CheckedInteger::multiply($unitMinor, $effective);

        return $this->detailedLine(
            $cartLine,
            $variant,
            $product,
            $locale,
            effectiveQuantity: $effective,
            status: $adjusted ? CartViewLine::STATUS_ADJUSTED : CartViewLine::STATUS_AVAILABLE,
            unavailableReason: null,
            lineTotalMinor: $lineMinor,
        );
    }

    /**
     * Public catalog details for storefront-visible products only.
     *
     * @param  int|null  $lineTotalMinor  null ⇒ no line total (e.g. visible out-of-stock)
     */
    private function detailedLine(
        CartLine $cartLine,
        ProductVariant $variant,
        Product $product,
        string $locale,
        int $effectiveQuantity,
        string $status,
        ?string $unavailableReason,
        ?int $lineTotalMinor,
    ): CartViewLine {
        $currency = $product->currency;
        $code = (string) $currency->code;
        $exponent = (int) $currency->exponent;
        $unitMinor = (int) $variant->price_amount_minor;
        $image = $product->primaryImage;

        return new CartViewLine(
            variantId: (int) $variant->id,
            productId: (int) $product->id,
            productSlug: (string) $product->slug,
            productName: LocalizedText::pick($product->translations, $locale, 'name', $product->slug) ?? $product->slug,
            storeName: (string) $product->store->name,
            storeSlug: (string) $product->store->slug,
            imageUrl: $this->imageUrl($product),
            imageAlt: $this->imageAlt($product, $locale),
            imageWidth: $image?->width !== null ? (int) $image->width : null,
            imageHeight: $image?->height !== null ? (int) $image->height : null,
            currencyCode: $code,
            currencyExponent: $exponent,
            unitPrice: $this->moneyPayload($code, $exponent, $unitMinor),
            lineTotal: $lineTotalMinor !== null
                ? $this->moneyPayload($code, $exponent, $lineTotalMinor)
                : null,
            selection: $this->selection($variant, $locale),
            requestedQuantity: $cartLine->quantity,
            effectiveQuantity: $effectiveQuantity,
            status: $status,
            unavailableReason: $unavailableReason,
        );
    }

    private function unavailablePlaceholder(CartLine $cartLine, string $reason): CartViewLine
    {
        return new CartViewLine(
            variantId: $cartLine->variantId,
            productId: 0,
            productSlug: '',
            productName: '',
            storeName: '',
            storeSlug: '',
            imageUrl: null,
            imageAlt: null,
            imageWidth: null,
            imageHeight: null,
            currencyCode: '',
            currencyExponent: 0,
            unitPrice: null,
            lineTotal: null,
            selection: [],
            requestedQuantity: $cartLine->quantity,
            effectiveQuantity: 0,
            status: CartViewLine::STATUS_UNAVAILABLE,
            unavailableReason: $reason,
        );
    }

    /**
     * @return list<array{attribute_code: string, attribute_name: string, value_code: string, value_name: string}>
     */
    private function selection(ProductVariant $variant, string $locale): array
    {
        $selection = [];

        foreach ($variant->attributeValueLinks as $link) {
            $attributeValue = $link->productAttributeValue->attributeValue;
            $attribute = $attributeValue->attribute;
            $selection[] = [
                'attribute_code' => (string) $attribute->code,
                'attribute_name' => LocalizedText::pick($attribute->translations, $locale, 'name', $attribute->code) ?? $attribute->code,
                'value_code' => (string) $attributeValue->code,
                'value_name' => LocalizedText::pick($attributeValue->translations, $locale, 'name', $attributeValue->code) ?? $attributeValue->code,
            ];
        }

        return $selection;
    }

    private function assertVariantGraph(ProductVariant $variant): void
    {
        if (! $variant->relationLoaded('product')) {
            throw new LogicException('CartViewPresenter requires loaded relation [product].');
        }

        if (! $variant->relationLoaded('attributeValueLinks')) {
            throw new LogicException('CartViewPresenter requires loaded relation [attributeValueLinks].');
        }

        foreach ($variant->attributeValueLinks as $link) {
            if (! $link->relationLoaded('productAttributeValue') || $link->productAttributeValue === null) {
                throw new LogicException('CartViewPresenter requires loaded relation [attributeValueLinks.productAttributeValue].');
            }

            $pav = $link->productAttributeValue;
            if (! $pav->relationLoaded('attributeValue') || $pav->attributeValue === null) {
                throw new LogicException('CartViewPresenter requires loaded relation [attributeValueLinks.productAttributeValue.attributeValue].');
            }

            $attributeValue = $pav->attributeValue;
            if (! $attributeValue->relationLoaded('translations')) {
                throw new LogicException('CartViewPresenter requires loaded relation [attributeValue.translations].');
            }

            if (! $attributeValue->relationLoaded('attribute') || $attributeValue->attribute === null) {
                throw new LogicException('CartViewPresenter requires loaded relation [attributeValue.attribute].');
            }

            if (! $attributeValue->attribute->relationLoaded('translations')) {
                throw new LogicException('CartViewPresenter requires loaded relation [attribute.translations].');
            }
        }

        $product = $variant->product;
        if ($product === null) {
            return;
        }

        foreach (['translations', 'currency', 'store', 'primaryImage'] as $relation) {
            if (! $product->relationLoaded($relation)) {
                throw new LogicException("CartViewPresenter requires loaded relation [product.{$relation}].");
            }
        }

        if ($product->primaryImage !== null && ! $product->primaryImage->relationLoaded('translations')) {
            throw new LogicException('CartViewPresenter requires loaded relation [product.primaryImage.translations].');
        }
    }

    private function imageUrl(Product $product): ?string
    {
        $path = $product->primaryImage?->path;

        return $path ? Storage::disk('public')->url($path) : null;
    }

    private function imageAlt(Product $product, string $locale): ?string
    {
        $image = $product->primaryImage;
        if ($image === null) {
            return null;
        }

        return LocalizedText::pick($image->translations, $locale, 'alt_text')
            ?? LocalizedText::pick($product->translations, $locale, 'name', $product->slug);
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
}
