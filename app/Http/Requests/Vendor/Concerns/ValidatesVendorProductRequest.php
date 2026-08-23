<?php

namespace App\Http\Requests\Vendor\Concerns;

use App\Enums\ProductType;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Services\ProductVariantMatrixService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesVendorProductRequest
{
    protected function requestedType(?Product $product = null): string
    {
        $fallback = $product?->type->value ?? ProductType::Simple->value;

        return strtolower(trim((string) ($this->input('type') ?: $fallback)));
    }

    protected function isVariableRequest(?Product $product = null): bool
    {
        return $this->requestedType($product) === ProductType::Variable->value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function commonProductRules(?Product $product = null): array
    {
        $typeRule = $product
            ? Rule::in([$product->type->value])
            : Rule::in([ProductType::Simple->value, ProductType::Variable->value]);

        $slug = [
            $product ? 'required' : 'nullable',
            'string',
            'max:160',
            'alpha_dash:ascii',
            Rule::unique('products', 'slug')->ignore($product?->id),
        ];

        $currency = [
            $product ? 'required' : 'nullable',
            'string',
            'size:3',
        ];

        if ($product !== null) {
            $currentCurrency = strtoupper((string) $product->currency_code);
            $currency[] = Rule::exists('currencies', 'code')->where(function ($query) use ($currentCurrency): void {
                $query->where('is_active', true)->orWhere('code', $currentCurrency);
            });
        } else {
            $currency[] = Rule::exists('currencies', 'code')->where(fn ($query) => $query->where('is_active', true));
        }

        return [
            'type' => ['required', 'string', $typeRule],
            'slug' => $slug,
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'currency_code' => $currency,
            'translations' => ['required', 'array'],
            'translations.ar.name' => ['nullable', 'string', 'max:160'],
            'translations.ar.short_description' => ['nullable', 'string', 'max:500'],
            'translations.ar.description' => ['nullable', 'string', 'max:10000'],
            'translations.en.name' => ['nullable', 'string', 'max:160'],
            'translations.en.short_description' => ['nullable', 'string', 'max:500'],
            'translations.en.description' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function simpleVariantRules(?Product $product = null): array
    {
        if ($this->isVariableRequest($product)) {
            return [
                'sku' => ['nullable', 'string', 'max:64'],
                'price' => ['nullable', 'string', 'max:32'],
                'compare_at_price' => ['nullable', 'string', 'max:32'],
                'quantity' => ['nullable', 'integer', 'min:0', 'max:'.ProductVariant::MAX_QUANTITY],
            ];
        }

        $storeId = $product?->store_id ?? $this->user()?->vendor?->store?->id;
        $variantId = $product?->defaultVariant?->id;

        $skuUnique = Rule::unique('product_variants', 'sku')->where(fn ($query) => $query->where('store_id', $storeId));
        if ($variantId) {
            $skuUnique->ignore($variantId);
        }

        return [
            'sku' => ['required', 'string', 'max:64', $skuUnique],
            'price' => ['required', 'string', 'max:32'],
            'compare_at_price' => ['nullable', 'string', 'max:32'],
            'quantity' => ['required', 'integer', 'min:0', 'max:'.ProductVariant::MAX_QUANTITY],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function variableMatrixRules(?Product $product = null, bool $requireActiveGlobals = true): array
    {
        if (! $this->isVariableRequest($product)) {
            return [];
        }

        $attributeExists = $requireActiveGlobals
            ? Rule::exists('attributes', 'id')->where(fn ($query) => $query->where('is_active', true))
            : Rule::exists('attributes', 'id');

        $valueExists = $requireActiveGlobals
            ? Rule::exists('attribute_values', 'id')->where(fn ($query) => $query->where('is_active', true))
            : Rule::exists('attribute_values', 'id');

        return [
            'attributes' => ['required', 'array', 'min:1', 'max:'.ProductAttribute::MAX_PER_PRODUCT],
            'attributes.*.attribute_id' => ['required', 'integer', 'distinct', $attributeExists],
            'attributes.*.value_ids' => ['required', 'array', 'min:1', 'max:'.ProductAttributeValue::MAX_PER_ATTRIBUTE],
            'attributes.*.value_ids.*' => ['required', 'integer', 'distinct', $valueExists],
            'variants' => ['required', 'array', 'min:1', 'max:'.ProductVariant::MAX_LIVE_PER_PRODUCT],
            'variants.*.value_ids' => ['required', 'array', 'min:1'],
            'variants.*.value_ids.*' => ['required', 'integer', 'distinct'],
            'variants.*.sku' => ['required', 'string', 'max:64'],
            'variants.*.price' => ['required', 'string', 'max:32'],
            'variants.*.compare_at_price' => ['nullable', 'string', 'max:32'],
            'variants.*.quantity' => ['required', 'integer', 'min:0', 'max:'.ProductVariant::MAX_QUANTITY],
            'variants.*.is_default' => ['sometimes'],
        ];
    }

    protected function normalizeVendorProductInput(?Product $product = null): void
    {
        $sku = $this->input('sku');
        $currency = $this->input('currency_code');
        $variants = $this->input('variants');

        if (is_array($variants)) {
            foreach ($variants as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                if (isset($row['sku']) && is_string($row['sku'])) {
                    $variants[$index]['sku'] = strtoupper(trim($row['sku']));
                }

                if (array_key_exists('compare_at_price', $row) && ! filled($row['compare_at_price'])) {
                    $variants[$index]['compare_at_price'] = null;
                }
            }
        }

        $this->merge([
            'sku' => is_string($sku) ? strtoupper(trim($sku)) : $sku,
            'currency_code' => is_string($currency) ? strtoupper(trim($currency)) : $currency,
            'type' => $this->requestedType($product),
            'category_id' => $this->filled('category_id') ? $this->input('category_id') : null,
            'brand_id' => $this->filled('brand_id') ? $this->input('brand_id') : null,
            'compare_at_price' => $this->filled('compare_at_price') ? $this->input('compare_at_price') : null,
            'slug' => $this->filled('slug') ? $this->input('slug') : null,
            'variants' => $variants,
        ]);
    }

    protected function afterVendorProductValidation(Validator $validator, ?Product $product = null): void
    {
        $ar = trim((string) $this->input('translations.ar.name', ''));
        $en = trim((string) $this->input('translations.en.name', ''));

        if ($ar === '' && $en === '') {
            $validator->errors()->add(
                'translations',
                __('Provide at least an Arabic or English product name.'),
            );
        }

        if (! $this->isVariableRequest($product) || $validator->errors()->isNotEmpty()) {
            return;
        }

        $attributes = $this->input('attributes', []);
        $cartesian = 1;
        $seenAttributeIds = [];

        if (! is_array($attributes) || $attributes === []) {
            return;
        }

        foreach ($attributes as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $attributeId = (int) ($row['attribute_id'] ?? 0);
            if ($attributeId > 0) {
                if (isset($seenAttributeIds[$attributeId])) {
                    $validator->errors()->add(
                        "attributes.{$index}.attribute_id",
                        __('Duplicate attributes are not allowed.'),
                    );
                }

                $seenAttributeIds[$attributeId] = true;
            }

            $valueIds = array_values(array_unique(array_map('intval', $row['value_ids'] ?? [])));
            $cartesian *= max(count($valueIds), 0);

            if ($cartesian > ProductVariantMatrixService::MAX_CARTESIAN) {
                $validator->errors()->add(
                    'attributes',
                    __('The attribute combination count may not exceed :max.', [
                        'max' => ProductVariantMatrixService::MAX_CARTESIAN,
                    ]),
                );

                return;
            }

            foreach ($valueIds as $valueId) {
                $value = AttributeValue::query()->find($valueId);
                if ($value === null || $value->attribute_id !== $attributeId) {
                    $validator->errors()->add(
                        "attributes.{$index}.value_ids",
                        __('Each selected value must belong to its attribute.'),
                    );
                    break;
                }
            }
        }

        $variants = $this->input('variants', []);
        if (! is_array($variants)) {
            return;
        }

        $defaults = 0;
        foreach ($variants as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            if ($this->truthy($row['is_default'] ?? false)) {
                $defaults++;
            }

            $valueIds = array_map('intval', $row['value_ids'] ?? []);
            if (count($valueIds) !== count(array_unique($valueIds))) {
                $validator->errors()->add(
                    "variants.{$index}.value_ids",
                    __('Duplicate values are not allowed.'),
                );
            }
        }

        if ($defaults !== 1) {
            $validator->errors()->add(
                'default_variant',
                __('Exactly one default variant is required.'),
            );
        }
    }

    /**
     * @return array<string, string>
     */
    protected function vendorProductAttributes(): array
    {
        return [
            'translations.ar.name' => __('Arabic name'),
            'translations.en.name' => __('English name'),
            'translations.ar.short_description' => __('Arabic short description'),
            'translations.en.short_description' => __('English short description'),
            'translations.ar.description' => __('Arabic description'),
            'translations.en.description' => __('English description'),
            'slug' => __('Slug'),
            'category_id' => __('Category'),
            'brand_id' => __('Brand'),
            'currency_code' => __('Currency'),
            'sku' => __('SKU'),
            'price' => __('Price'),
            'compare_at_price' => __('Compare-at price'),
            'quantity' => __('Quantity'),
            'type' => __('Product type'),
            'attributes' => __('Attributes'),
            'variants' => __('Variants'),
            'default_variant' => __('Default variant'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function vendorProductMessages(?Product $product = null): array
    {
        return [
            'type.in' => $product
                ? __('Product type cannot be changed after creation.')
                : __('Select a simple or variable product type.'),
        ];
    }

    private function truthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }
}
