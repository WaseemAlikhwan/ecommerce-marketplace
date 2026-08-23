<?php

namespace App\Storefront\Presentation;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Storefront\StorefrontProductQuery;
use App\Support\Locale;
use App\Support\LocalizedText;
use App\Support\Money;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Builds ProductDetailState from an already-loaded Product graph.
 * Performs zero database queries.
 */
final class ProductDetailPresenter
{
    public function __construct(
        private readonly ProductCardPresenter $cardPresenter = new ProductCardPresenter,
    ) {}

    public function present(Product $product, ?string $locale = null): ProductDetailState
    {
        $locale = Locale::sanitize($locale ?? app()->getLocale());
        $this->assertDetailPayload($product);

        $name = LocalizedText::pick($product->translations, $locale, 'name', $product->slug) ?? $product->slug;
        $shortDescription = LocalizedText::pick($product->translations, $locale, 'short_description');
        $description = LocalizedText::pick($product->translations, $locale, 'description');

        $currency = $product->currency;
        $exponent = (int) $currency->exponent;
        $currencyCode = (string) $currency->code;
        $store = $product->store;

        $defaultVariant = $product->variants->first(
            fn (ProductVariant $variant): bool => (int) $variant->id === (int) $product->default_variant_id
        );

        if ($defaultVariant === null) {
            throw new LogicException('ProductDetailPresenter requires a live default Variant among loaded variants.');
        }

        $selectedPriceMinor = (int) $defaultVariant->price_amount_minor;

        $selectedCompareMinor = $defaultVariant->compare_at_amount_minor;
        $selectedCompareAt = null;
        $selectedCompareAtLabel = null;
        if ($selectedCompareMinor !== null && (int) $selectedCompareMinor > $selectedPriceMinor) {
            $selectedCompareAt = $this->moneyPayload($currencyCode, $exponent, (int) $selectedCompareMinor);
            $selectedCompareAtLabel = $this->formatMoneyLabel($currencyCode, $exponent, (int) $selectedCompareMinor);
        }

        $minPriceMinor = (int) $product->variants->min('price_amount_minor');
        $maxPriceMinor = (int) $product->variants->max('price_amount_minor');
        $minPriceLabel = $this->formatMoneyLabel($currencyCode, $exponent, $minPriceMinor);
        $maxPriceLabel = $this->formatMoneyLabel($currencyCode, $exponent, $maxPriceMinor);
        $priceRangeLabel = $minPriceMinor === $maxPriceMinor
            ? $minPriceLabel
            : $minPriceLabel.' – '.$maxPriceLabel;
        $inStock = (int) $defaultVariant->quantity > 0;

        $related = [];
        foreach ($product->relatedStorefrontProducts as $relatedProduct) {
            $related[] = $this->cardPresenter->present($relatedProduct, $locale)->toArray();
        }

        return new ProductDetailState(
            id: (int) $product->id,
            slug: (string) $product->slug,
            url: route('storefront.product', $product->slug),
            type: $product->type->value,
            name: $name,
            shortDescription: $shortDescription,
            description: $description,
            breadcrumbs: $this->breadcrumbs($product, $locale),
            store: [
                'name' => (string) $store->name,
                'slug' => (string) $store->slug,
                'url' => route('storefront.store', $store->slug),
                'description' => $store->description !== null ? (string) $store->description : null,
                'logo_url' => $store->logo_path ? Storage::disk('public')->url($store->logo_path) : null,
                'initials' => $this->initials((string) $store->name),
            ],
            brandName: $product->brand !== null
                ? (LocalizedText::pick($product->brand->translations, $locale, 'name', $product->brand->slug) ?? $product->brand->slug)
                : null,
            brandSlug: $product->brand?->slug,
            gallery: $this->gallery($product, $locale, $name),
            attributes: $this->attributes($product, $locale),
            variants: $this->variants($product, $locale, $currencyCode, $exponent),
            defaultVariant: $this->variantPayload($defaultVariant, $locale, $currencyCode, $exponent),
            currencyCode: $currencyCode,
            currencyExponent: $exponent,
            selectedPrice: $this->moneyPayload($currencyCode, $exponent, $selectedPriceMinor),
            selectedPriceLabel: $this->formatMoneyLabel($currencyCode, $exponent, $selectedPriceMinor),
            minPriceLabel: $minPriceLabel,
            maxPriceLabel: $maxPriceLabel,
            priceRangeLabel: $priceRangeLabel,
            selectedCompareAt: $selectedCompareAt,
            selectedCompareAtLabel: $selectedCompareAtLabel,
            inStock: $inStock,
            related: $related,
        );
    }

    /**
     * @return list<array{slug: string, name: string, url: string}>
     */
    private function breadcrumbs(Product $product, string $locale): array
    {
        $crumbs = [];
        $category = $product->category;
        $chain = [];
        while ($category !== null) {
            array_unshift($chain, $category);
            $category = $category->relationLoaded('parent') ? $category->parent : null;
        }

        foreach ($chain as $node) {
            /** @var Category $node */
            $crumbs[] = [
                'slug' => (string) $node->slug,
                'name' => LocalizedText::pick($node->translations, $locale, 'name', $node->slug) ?? $node->slug,
                'url' => route('storefront.category', $node->slug),
            ];
        }

        return $crumbs;
    }

    /**
     * @return list<array{id: int, url: string, alt: string, width: ?int, height: ?int, position: int, is_primary: bool}>
     */
    private function gallery(Product $product, string $locale, string $productName): array
    {
        $primaryId = $product->primary_image_id;
        $items = [];
        foreach ($product->images as $image) {
            $items[] = [
                'id' => (int) $image->id,
                'url' => Storage::disk('public')->url($image->path),
                'alt' => LocalizedText::pick($image->translations, $locale, 'alt_text') ?? $productName,
                'width' => $image->width !== null ? (int) $image->width : null,
                'height' => $image->height !== null ? (int) $image->height : null,
                'position' => (int) $image->position,
                'is_primary' => $primaryId !== null && (int) $image->id === (int) $primaryId,
            ];
        }

        return $items;
    }

    /**
     * @return list<array{code: string, name: string, values: list<array{code: string, name: string}>}>
     */
    private function attributes(Product $product, string $locale): array
    {
        if ($product->type !== ProductType::Variable) {
            return [];
        }

        $rows = [];
        foreach ($product->productAttributes as $productAttribute) {
            $attribute = $productAttribute->attribute;
            $values = [];
            foreach ($productAttribute->selectedValues as $selected) {
                $attributeValue = $selected->attributeValue;
                $values[] = [
                    'code' => (string) $attributeValue->code,
                    'name' => LocalizedText::pick($attributeValue->translations, $locale, 'name', $attributeValue->code) ?? $attributeValue->code,
                ];
            }
            $rows[] = [
                'code' => (string) $attribute->code,
                'name' => LocalizedText::pick($attribute->translations, $locale, 'name', $attribute->code) ?? $attribute->code,
                'values' => $values,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function variants(Product $product, string $locale, string $currencyCode, int $exponent): array
    {
        $rows = [];
        foreach ($product->variants as $variant) {
            $rows[] = $this->variantPayload($variant, $locale, $currencyCode, $exponent);
        }

        return $rows;
    }

    /**
     * Public Variant payload: id, selection, prices, compare-at, boolean in_stock.
     * Exact quantity and SKU remain server-authoritative (Cart revalidates later).
     *
     * @return array<string, mixed>
     */
    private function variantPayload(ProductVariant $variant, string $locale, string $currencyCode, int $exponent): array
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

        $priceMinor = (int) $variant->price_amount_minor;
        $compareMinor = $variant->compare_at_amount_minor;
        $compareAt = null;
        $compareAtLabel = null;
        if ($compareMinor !== null && (int) $compareMinor > $priceMinor) {
            $compareAt = $this->moneyPayload($currencyCode, $exponent, (int) $compareMinor);
            $compareAtLabel = $this->formatMoneyLabel($currencyCode, $exponent, (int) $compareMinor);
        }

        return [
            'id' => (int) $variant->id,
            'in_stock' => (int) $variant->quantity > 0,
            'price' => $this->moneyPayload($currencyCode, $exponent, $priceMinor),
            'price_label' => $this->formatMoneyLabel($currencyCode, $exponent, $priceMinor),
            'compare_at' => $compareAt,
            'compare_at_label' => $compareAtLabel,
            'selection' => $selection,
        ];
    }

    private function assertDetailPayload(Product $product): void
    {
        foreach ([
            'translations',
            'store',
            'currency',
            'images',
            'variants',
            'relatedStorefrontProducts',
        ] as $relation) {
            if (! $product->relationLoaded($relation)) {
                throw new LogicException("ProductDetailPresenter requires loaded relation [{$relation}].");
            }
        }

        if ($product->variants->isEmpty()) {
            throw new LogicException('ProductDetailPresenter requires at least one live Variant.');
        }

        if ($product->brand_id !== null) {
            if (! $product->relationLoaded('brand') || $product->brand === null) {
                throw new LogicException('ProductDetailPresenter requires loaded relation [brand].');
            }
            if (! $product->brand->relationLoaded('translations')) {
                throw new LogicException('ProductDetailPresenter requires loaded relation [brand.translations].');
            }
        }

        if ($product->category_id !== null) {
            if (! $product->relationLoaded('category') || $product->category === null) {
                throw new LogicException('ProductDetailPresenter requires loaded relation [category].');
            }
            $this->assertCategoryAncestorTranslations($product->category);
        }

        foreach ($product->images as $image) {
            if (! $image->relationLoaded('translations')) {
                throw new LogicException('ProductDetailPresenter requires loaded relation [images.translations].');
            }
        }

        foreach ($product->variants as $variant) {
            if (! $variant->relationLoaded('attributeValueLinks')) {
                throw new LogicException('ProductDetailPresenter requires loaded relation [variants.attributeValueLinks].');
            }
            foreach ($variant->attributeValueLinks as $link) {
                if (! $link->relationLoaded('productAttributeValue') || $link->productAttributeValue === null) {
                    throw new LogicException('ProductDetailPresenter requires loaded relation [variants.attributeValueLinks.productAttributeValue].');
                }
                $pav = $link->productAttributeValue;
                if (! $pav->relationLoaded('attributeValue') || $pav->attributeValue === null) {
                    throw new LogicException('ProductDetailPresenter requires loaded nested Variant Attribute Value.');
                }
                $attributeValue = $pav->attributeValue;
                if (! $attributeValue->relationLoaded('translations')) {
                    throw new LogicException('ProductDetailPresenter requires loaded Variant Attribute Value translations.');
                }
                if (! $attributeValue->relationLoaded('attribute') || $attributeValue->attribute === null) {
                    throw new LogicException('ProductDetailPresenter requires loaded Variant Attribute.');
                }
                if (! $attributeValue->attribute->relationLoaded('translations')) {
                    throw new LogicException('ProductDetailPresenter requires loaded Variant Attribute translations.');
                }
            }
        }

        if ($product->type === ProductType::Variable) {
            if (! $product->relationLoaded('productAttributes')) {
                throw new LogicException('ProductDetailPresenter requires loaded relation [productAttributes].');
            }
            foreach ($product->productAttributes as $productAttribute) {
                if (! $productAttribute->relationLoaded('attribute') || $productAttribute->attribute === null) {
                    throw new LogicException('ProductDetailPresenter requires loaded relation [productAttributes.attribute].');
                }
                if (! $productAttribute->attribute->relationLoaded('translations')) {
                    throw new LogicException('ProductDetailPresenter requires loaded Attribute translations.');
                }
                if (! $productAttribute->relationLoaded('selectedValues')) {
                    throw new LogicException('ProductDetailPresenter requires loaded relation [productAttributes.selectedValues].');
                }
                foreach ($productAttribute->selectedValues as $selected) {
                    if (! $selected->relationLoaded('attributeValue') || $selected->attributeValue === null) {
                        throw new LogicException('ProductDetailPresenter requires loaded selected Attribute Value.');
                    }
                    if (! $selected->attributeValue->relationLoaded('translations')) {
                        throw new LogicException('ProductDetailPresenter requires loaded selected Attribute Value translations.');
                    }
                }
            }
        }

        foreach ($product->relatedStorefrontProducts as $related) {
            foreach (['translations', 'store', 'currency', 'primaryImage'] as $relation) {
                if (! $related->relationLoaded($relation)) {
                    throw new LogicException("ProductDetailPresenter requires loaded related Product relation [{$relation}].");
                }
            }
            if ($related->primaryImage !== null && ! $related->primaryImage->relationLoaded('translations')) {
                throw new LogicException('ProductDetailPresenter requires loaded related Product primaryImage.translations.');
            }
            $aggregateAttributes = $related->getAttributes();
            foreach ([
                StorefrontProductQuery::AGG_MIN_PRICE,
                StorefrontProductQuery::AGG_MAX_PRICE,
                StorefrontProductQuery::AGG_IN_STOCK,
                StorefrontProductQuery::AGG_COMPARE_AT,
            ] as $attribute) {
                if (! array_key_exists($attribute, $aggregateAttributes)) {
                    throw new LogicException("ProductDetailPresenter requires related Product aggregate [{$attribute}].");
                }
            }
            foreach ([StorefrontProductQuery::AGG_MIN_PRICE, StorefrontProductQuery::AGG_MAX_PRICE] as $attribute) {
                if ($aggregateAttributes[$attribute] === null) {
                    throw new LogicException("ProductDetailPresenter requires non-null related Product aggregate [{$attribute}].");
                }
            }
            if ($aggregateAttributes[StorefrontProductQuery::AGG_IN_STOCK] === null) {
                throw new LogicException('ProductDetailPresenter requires non-null related Product aggregate ['.StorefrontProductQuery::AGG_IN_STOCK.'].');
            }
        }
    }

    private function assertCategoryAncestorTranslations(Category $category): void
    {
        if (! $category->relationLoaded('translations')) {
            throw new LogicException('ProductDetailPresenter requires loaded relation [category.translations].');
        }

        $node = $category;
        while ($node->parent_id !== null) {
            if (! $node->relationLoaded('parent') || $node->parent === null) {
                throw new LogicException('ProductDetailPresenter requires each declared Category ancestor to be loaded.');
            }

            $node = $node->parent;
            if (! $node->relationLoaded('translations')) {
                throw new LogicException('ProductDetailPresenter requires loaded Category ancestor translations.');
            }
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

    private function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = array_map(
            static fn (string $word): string => mb_substr($word, 0, 1),
            array_slice($words, 0, 2),
        );

        return mb_strtoupper(implode('', $letters) ?: mb_substr($name, 0, 1));
    }
}
