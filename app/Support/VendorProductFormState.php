<?php

namespace App\Support;

use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Services\ProductVariantMatrixService;

final class VendorProductFormState
{
    /**
     * @return array<string, mixed>
     */
    public static function bootstrap(?Product $product, string $currencyCode, bool $canEdit): array
    {
        $locale = app()->getLocale();
        $dictionary = self::dictionary($product, $locale);
        $selected = self::selectedState($product);
        $rows = self::variantRows($product, $dictionary, $locale);

        if (old('type') || old('attributes') || old('variants')) {
            $overlay = self::fromOldInput($dictionary);
            $selected['attribute_ids'] = $overlay['attribute_ids'];
            $selected['value_ids'] = $overlay['value_ids'];
            $rows = $overlay['rows'] !== [] ? $overlay['rows'] : $rows;
        }

        $exponents = Currency::query()->active()->get(['code', 'exponent'])
            ->mapWithKeys(fn (Currency $currency) => [$currency->code => (int) $currency->exponent])
            ->all();

        return [
            'type' => old('type', $product?->type->value ?? ProductType::Simple->value),
            'lockedType' => $product !== null,
            'canEdit' => $canEdit,
            'frozen' => $product !== null && ! $product->allowsStructuralMatrixSync(),
            'currencyCode' => old('currency_code', $product?->currency_code ?? $currencyCode),
            'initialCurrencyCode' => $product?->currency_code ?? $currencyCode,
            'exponents' => $exponents,
            'maxAttributes' => ProductAttribute::MAX_PER_PRODUCT,
            'maxValues' => ProductAttributeValue::MAX_PER_ATTRIBUTE,
            'maxCartesian' => ProductVariantMatrixService::MAX_CARTESIAN,
            'dictionary' => $dictionary,
            'selectedAttributeIds' => $selected['attribute_ids'],
            'selectedValueIds' => $selected['value_ids'],
            'rows' => $rows,
            'generated' => $rows !== [],
            'initialDirty' => session()->hasOldInput(),
            'labels' => self::labels(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function dictionary(?Product $product, ?string $locale = null): array
    {
        $locale = Locale::sanitize($locale ?? app()->getLocale());

        $attributes = Attribute::query()
            ->active()
            ->ordered()
            ->with(['translations', 'values' => fn ($query) => $query->active()->ordered()->with('translations')])
            ->get();

        $byId = $attributes->keyBy('id');

        if ($product?->type === ProductType::Variable) {
            $product->loadMissing([
                'productAttributesWithTrashed.attribute.translations',
                'productAttributesWithTrashed.selectedValuesWithTrashed.attributeValue.translations',
            ]);

            foreach ($product->productAttributesWithTrashed as $assignment) {
                $attribute = $assignment->attribute;
                if ($attribute === null) {
                    continue;
                }

                if (! $byId->has($attribute->id)) {
                    $attribute->setRelation(
                        'values',
                        AttributeValue::query()
                            ->where('attribute_id', $attribute->id)
                            ->ordered()
                            ->with('translations')
                            ->get()
                            ->filter(function (AttributeValue $value) use ($assignment): bool {
                                return $value->is_active || $assignment->selectedValuesWithTrashed
                                    ->contains('attribute_value_id', $value->id);
                            })
                            ->values(),
                    );
                    $byId->put($attribute->id, $attribute);
                } else {
                    $existing = $byId->get($attribute->id);
                    $valueIds = $existing->values->pluck('id');
                    foreach ($assignment->selectedValuesWithTrashed as $selected) {
                        $value = $selected->attributeValue;
                        if ($value && ! $valueIds->contains($value->id)) {
                            $existing->values->push($value);
                        }
                    }
                }
            }
        }

        return $byId->values()->map(fn (Attribute $attribute) => self::presentAttribute($attribute, $locale))->all();
    }

    /**
     * @return array{attribute_ids: list<int>, value_ids: array<int, list<int>>}
     */
    private static function selectedState(?Product $product): array
    {
        if ($product === null || $product->type !== ProductType::Variable) {
            return ['attribute_ids' => [], 'value_ids' => []];
        }

        $attributeIds = [];
        $valueIds = [];

        foreach ($product->productAttributes()->ordered()->with('selectedValues')->get() as $assignment) {
            $attributeIds[] = $assignment->attribute_id;
            $valueIds[$assignment->attribute_id] = $assignment->selectedValues
                ->pluck('attribute_value_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        return [
            'attribute_ids' => $attributeIds,
            'value_ids' => $valueIds,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $dictionary
     * @return list<array<string, mixed>>
     */
    private static function variantRows(?Product $product, array $dictionary, string $locale): array
    {
        if ($product === null || $product->type !== ProductType::Variable) {
            return [];
        }

        $product->loadMissing([
            'variantsWithTrashed.attributeValueLinks.productAttributeValue',
            'currency',
        ]);

        $exponent = (int) ($product->currency?->exponent ?? 0);
        $dictionaryById = [];
        foreach ($dictionary as $attribute) {
            $dictionaryById[(int) $attribute['id']] = $attribute;
        }
        $rows = [];

        foreach ($product->variantsWithTrashed as $variant) {
            $map = [];
            foreach ($variant->attributeValueLinks as $link) {
                $selected = $link->productAttributeValue;
                if ($selected === null) {
                    continue;
                }
                $map[(int) $selected->attribute_id] = (int) $selected->attribute_value_id;
            }

            if ($map === [] && $variant->combination_key === ProductVariant::DEFAULT_COMBINATION_KEY) {
                continue;
            }

            ksort($map, SORT_NUMERIC);
            $valueIds = array_values($map);
            $inactive = self::combinationHasInactiveGlobals($map, $dictionaryById);
            $archived = $variant->trashed();

            $rows[] = [
                'key' => implode('|', $valueIds),
                'valueIds' => $valueIds,
                'valueMap' => $map,
                'sku' => $variant->sku,
                'price' => Money::formatFromMinor((int) $variant->price_amount_minor, $exponent),
                'compareAt' => $variant->compare_at_amount_minor === null
                    ? ''
                    : Money::formatFromMinor((int) $variant->compare_at_amount_minor, $exponent),
                'quantity' => (int) $variant->quantity,
                'included' => ! $archived,
                'isDefault' => ! $archived && (int) $product->default_variant_id === (int) $variant->id,
                'persisted' => true,
                'archived' => $archived,
                'canRestore' => $archived && ! $inactive,
                'inactiveGlobals' => $inactive,
                'chips' => self::chipsForMap($map, $locale, $dictionaryById),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $dictionary
     * @return array{attribute_ids: list<int>, value_ids: array<int, list<int>>, rows: list<array<string, mixed>>}
     */
    private static function fromOldInput(array $dictionary): array
    {
        $attributeIds = [];
        $valueIds = [];
        $dictionaryById = collect($dictionary)->keyBy('id');

        foreach ((array) old('attributes', []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $attributeId = (int) ($row['attribute_id'] ?? 0);
            if ($attributeId < 1) {
                continue;
            }
            $attributeIds[] = $attributeId;
            $valueIds[$attributeId] = array_values(array_unique(array_map('intval', $row['value_ids'] ?? [])));
        }

        $rows = [];
        foreach ((array) old('variants', []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $ids = array_values(array_unique(array_map('intval', $row['value_ids'] ?? [])));
            $map = [];
            foreach ($ids as $valueId) {
                foreach ($dictionaryById as $attribute) {
                    foreach ($attribute['values'] as $value) {
                        if ((int) $value['id'] === $valueId) {
                            $map[(int) $attribute['id']] = $valueId;
                        }
                    }
                }
            }
            ksort($map, SORT_NUMERIC);

            $rows[] = [
                'key' => implode('|', array_values($map)),
                'valueIds' => array_values($map),
                'valueMap' => $map,
                'sku' => (string) ($row['sku'] ?? ''),
                'price' => (string) ($row['price'] ?? ''),
                'compareAt' => (string) ($row['compare_at_price'] ?? ''),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'included' => true,
                'isDefault' => self::truthy($row['is_default'] ?? false),
                'persisted' => false,
                'archived' => false,
                'canRestore' => false,
                'inactiveGlobals' => false,
                'chips' => self::chipsForMap($map, app()->getLocale(), $dictionaryById->all()),
            ];
        }

        return [
            'attribute_ids' => $attributeIds,
            'value_ids' => $valueIds,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<int, int>  $map
     * @param  array<int, array<string, mixed>>  $dictionaryById
     */
    private static function combinationHasInactiveGlobals(array $map, array $dictionaryById): bool
    {
        foreach ($map as $attributeId => $valueId) {
            $attribute = $dictionaryById[$attributeId] ?? null;
            if (($attribute['isActive'] ?? true) === false) {
                return true;
            }

            $value = collect($attribute['values'] ?? [])->firstWhere('id', $valueId);
            if ($value === null || ($value['isActive'] ?? true) === false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, int>  $map
     * @param  array<int, array<string, mixed>>|null  $dictionary
     * @return list<array{label: string, code: string, inactive: bool}>
     */
    private static function chipsForMap(array $map, string $locale, ?array $dictionary = null): array
    {
        $chips = [];

        if ($dictionary !== null) {
            foreach ($map as $attributeId => $valueId) {
                $attribute = $dictionary[$attributeId] ?? null;
                if ($attribute === null) {
                    continue;
                }
                $value = collect($attribute['values'])->firstWhere('id', $valueId);
                $chips[] = [
                    'label' => ($attribute['name'] ?? '').': '.($value['name'] ?? (string) $valueId),
                    'code' => (string) ($value['code'] ?? ''),
                    'inactive' => (bool) (($attribute['isActive'] ?? true) === false || ($value['isActive'] ?? true) === false),
                ];
            }

            return $chips;
        }

        $attributes = Attribute::query()->whereIn('id', array_keys($map))->with('translations')->get()->keyBy('id');
        $values = AttributeValue::query()->whereIn('id', array_values($map))->with('translations')->get()->keyBy('id');

        foreach ($map as $attributeId => $valueId) {
            $attribute = $attributes->get($attributeId);
            $value = $values->get($valueId);
            $chips[] = [
                'label' => ($attribute?->name($locale) ?? '').': '.($value?->name($locale) ?? (string) $valueId),
                'code' => (string) ($value?->code ?? ''),
                'inactive' => ($attribute?->is_active === false) || ($value?->is_active === false),
            ];
        }

        return $chips;
    }

    /**
     * @return array<string, mixed>
     */
    private static function presentAttribute(Attribute $attribute, string $locale): array
    {
        return [
            'id' => $attribute->id,
            'code' => $attribute->code,
            'name' => $attribute->name($locale),
            'nameAr' => $attribute->name('ar'),
            'nameEn' => $attribute->name('en'),
            'isActive' => (bool) $attribute->is_active,
            'values' => $attribute->values->map(fn (AttributeValue $value) => [
                'id' => $value->id,
                'code' => $value->code,
                'name' => $value->name($locale),
                'nameAr' => $value->name('ar'),
                'nameEn' => $value->name('en'),
                'isActive' => (bool) $value->is_active,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function labels(): array
    {
        return [
            'combinations' => __('Combinations'),
            'generate' => __('Generate combinations'),
            'tooMany' => __('Too many combinations. Reduce attributes or values to 48 or fewer.'),
            'skuPrefix' => __('SKU prefix'),
            'fillMissingSkus' => __('Fill missing SKUs'),
            'applyPrice' => __('Apply price to blank rows'),
            'applyCompare' => __('Apply compare-at to blank rows'),
            'applyQuantity' => __('Apply quantity to blank rows'),
            'include' => __('Include'),
            'default' => __('Default'),
            'archived' => __('Archived'),
            'inactive' => __('Inactive'),
            'excluded' => __('Excluded combinations'),
            'temporarilyExcluded' => __('Temporarily excluded'),
            'newCombination' => __('New combination'),
            'archivedCombination' => __('Archived combination'),
            'undoExclusion' => __('Undo exclusion'),
            'restoreArchived' => __('Restore archived combination'),
            'restoreBlocked' => __('Cannot restore while an attribute or value is inactive.'),
            'reloadConfirm' => __('Discard unsaved product details and reload the saved version?'),
        ];
    }

    /**
     * Session UI action for a currently excluded matrix row.
     * `archived` is persisted database state and must not be inferred from `included`.
     *
     * @param  array<string, mixed>  $row
     */
    public static function excludedRowAction(array $row): string
    {
        if (! empty($row['archived'])) {
            return ! empty($row['canRestore']) ? 'restore_archived' : 'restore_blocked';
        }

        return 'undo_exclusion';
    }

    private static function truthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }
}
