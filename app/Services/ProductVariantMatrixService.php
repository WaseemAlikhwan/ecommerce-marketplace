<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttributeValue;
use App\Support\CombinationKey;
use App\Support\VariantEconomics;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductVariantMatrixService
{
    public const MAX_CARTESIAN = 48;

    public function __construct(
        private readonly ProductReadinessService $readiness,
    ) {}

    /**
     * @param  array{
     *     attributes: list<array{attribute_id: int|string, value_ids: list<int|string>}>,
     *     variants: list<array{
     *         value_ids: list<int|string>,
     *         sku: string,
     *         price: string,
     *         compare_at_price?: string|null,
     *         quantity: int|string,
     *         is_default?: bool|int|string|null
     *     }>
     * }  $matrix
     */
    public function assertWithinLimits(array $matrix): int
    {
        $attributes = $matrix['attributes'] ?? null;

        if (! is_array($attributes) || $attributes === []) {
            throw ValidationException::withMessages([
                'attributes' => __('Select at least one attribute.'),
            ]);
        }

        if (count($attributes) > ProductAttribute::MAX_PER_PRODUCT) {
            throw ValidationException::withMessages([
                'attributes' => __('A variable product may have at most :max attributes.', [
                    'max' => ProductAttribute::MAX_PER_PRODUCT,
                ]),
            ]);
        }

        $seenAttributeIds = [];
        $cartesian = 1;

        foreach ($attributes as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages([
                    "attributes.{$index}" => __('Each attribute assignment is invalid.'),
                ]);
            }

            $attributeId = $this->positiveInt($row['attribute_id'] ?? null, "attributes.{$index}.attribute_id");

            if (isset($seenAttributeIds[$attributeId])) {
                throw ValidationException::withMessages([
                    "attributes.{$index}.attribute_id" => __('Duplicate attributes are not allowed.'),
                ]);
            }

            $seenAttributeIds[$attributeId] = true;
            $valueIds = $this->uniquePositiveInts($row['value_ids'] ?? null, "attributes.{$index}.value_ids");

            if ($valueIds === []) {
                throw ValidationException::withMessages([
                    "attributes.{$index}.value_ids" => __('Select at least one value for each attribute.'),
                ]);
            }

            if (count($valueIds) > ProductAttributeValue::MAX_PER_ATTRIBUTE) {
                throw ValidationException::withMessages([
                    "attributes.{$index}.value_ids" => __('An attribute may have at most :max selected values.', [
                        'max' => ProductAttributeValue::MAX_PER_ATTRIBUTE,
                    ]),
                ]);
            }

            $cartesian *= count($valueIds);

            if ($cartesian > self::MAX_CARTESIAN) {
                throw ValidationException::withMessages([
                    'attributes' => __('The attribute combination count may not exceed :max.', [
                        'max' => self::MAX_CARTESIAN,
                    ]),
                ]);
            }
        }

        $variants = $matrix['variants'] ?? null;

        if (! is_array($variants) || $variants === []) {
            throw ValidationException::withMessages([
                'variants' => __('A variable product must include at least one variant.'),
            ]);
        }

        if (count($variants) > ProductVariant::MAX_LIVE_PER_PRODUCT) {
            throw ValidationException::withMessages([
                'variants' => __('A variable product may have at most :max live variants.', [
                    'max' => ProductVariant::MAX_LIVE_PER_PRODUCT,
                ]),
            ]);
        }

        return $cartesian;
    }

    /**
     * @param  array{
     *     attributes: list<array{attribute_id: int|string, value_ids: list<int|string>}>,
     *     variants: list<array{
     *         value_ids: list<int|string>,
     *         sku: string,
     *         price: string,
     *         compare_at_price?: string|null,
     *         quantity: int|string,
     *         is_default?: bool|int|string|null
     *     }>
     * }  $matrix
     */
    public function sync(Product $product, array $matrix): Product
    {
        $this->assertWithinLimits($matrix);

        try {
            return DB::transaction(function () use ($product, $matrix): Product {
                /** @var Product $product */
                $product = Product::query()->lockForUpdate()->findOrFail($product->id);

                if ($product->type !== ProductType::Variable) {
                    throw ValidationException::withMessages([
                        'type' => __('Only variable products can use a variant matrix.'),
                    ]);
                }

                if (! $product->status->isVendorEditable()) {
                    throw ValidationException::withMessages([
                        'status' => __('This product cannot be edited in its current status.'),
                    ]);
                }

                $existingVariants = ProductVariant::withTrashed()
                    ->where('product_id', $product->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('combination_key');

                ProductAttribute::withTrashed()
                    ->where('product_id', $product->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                ProductAttributeValue::withTrashed()
                    ->where('product_id', $product->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $requestedAttributeIds = [];
                $requestedValueIds = [];

                foreach ($matrix['attributes'] as $row) {
                    $requestedAttributeIds[] = (int) $row['attribute_id'];
                    foreach ($row['value_ids'] as $valueId) {
                        $requestedValueIds[] = (int) $valueId;
                    }
                }

                foreach ($matrix['variants'] as $row) {
                    foreach ($row['value_ids'] ?? [] as $valueId) {
                        $requestedValueIds[] = (int) $valueId;
                    }
                }

                $requestedAttributeIds = array_values(array_unique($requestedAttributeIds));
                $requestedValueIds = array_values(array_unique(array_filter($requestedValueIds, fn (int $id): bool => $id > 0)));
                sort($requestedAttributeIds);
                sort($requestedValueIds);

                $globals = Attribute::query()
                    ->whereIn('id', $requestedAttributeIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $globalValues = AttributeValue::query()
                    ->whereIn('id', $requestedValueIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $parsedAttributes = $this->parseAttributes($matrix['attributes'], $globals, $globalValues);
                $parsedVariants = $this->parseVariants(
                    $matrix['variants'],
                    $parsedAttributes,
                    $globalValues,
                    (int) ($product->currency()->value('exponent') ?? 0),
                );

                if ($product->allowsStructuralMatrixSync()) {
                    $this->syncDraftTopology($product, $parsedAttributes, $globals, $globalValues);
                } else {
                    $this->assertFrozenTopology($product, $parsedAttributes);
                }

                $assignments = ProductAttribute::query()
                    ->where('product_id', $product->id)
                    ->orderBy('position')
                    ->orderBy('id')
                    ->get()
                    ->keyBy('attribute_id');

                $selectedValues = ProductAttributeValue::query()
                    ->where('product_id', $product->id)
                    ->get()
                    ->groupBy('product_attribute_id');

                $this->syncVariants(
                    $product,
                    $parsedVariants,
                    $existingVariants,
                    $assignments,
                    $selectedValues,
                    $globalValues,
                    $product->allowsStructuralMatrixSync(),
                );

                $default = ProductVariant::query()
                    ->where('product_id', $product->id)
                    ->where('combination_key', $parsedVariants['default_key'])
                    ->firstOrFail();

                $product->forceFill(['default_variant_id' => $default->id])->save();

                $liveCount = ProductVariant::query()->where('product_id', $product->id)->count();

                if ($liveCount < 1) {
                    throw ValidationException::withMessages([
                        'variants' => __('A product must keep at least one live variant.'),
                    ]);
                }

                $default = $default->fresh();

                if ($default === null || $default->trashed() || $default->product_id !== $product->id) {
                    throw ValidationException::withMessages([
                        'default_variant' => __('A live default variant is required.'),
                    ]);
                }

                return $this->finalizeSyncedProduct($product);
            });
        } catch (UniqueConstraintViolationException $exception) {
            VariantEconomics::rethrowUniqueConstraint($exception);
        }
    }

    private function finalizeSyncedProduct(Product $product): Product
    {
        $fresh = $product->fresh([
            'translations',
            'defaultVariant',
            'currency',
            'category',
            'brand',
            'productAttributes.attribute',
            'productAttributes.selectedValues.attributeValue',
            'variants.attributeValueLinks.productAttributeValue',
            'images',
            'primaryImage',
            'store.vendor',
        ]) ?? $product;

        $this->readiness->assertIntegrityForPublished($fresh);

        return $fresh;
    }

    /**
     * @param  list<array{attribute_id: int|string, value_ids: list<int|string>}>  $rows
     * @param  Collection<int, Attribute>  $globals
     * @param  Collection<int, AttributeValue>  $globalValues
     * @return list<array{attribute_id: int, value_ids: list<int>, value_set: array<int, true>}>
     */
    private function parseAttributes(array $rows, Collection $globals, Collection $globalValues): array
    {
        $parsed = [];

        foreach ($rows as $index => $row) {
            $attributeId = $this->positiveInt($row['attribute_id'] ?? null, "attributes.{$index}.attribute_id");
            $attribute = $globals->get($attributeId);

            if ($attribute === null) {
                throw ValidationException::withMessages([
                    "attributes.{$index}.attribute_id" => __('The selected attribute is invalid.'),
                ]);
            }

            $valueIds = $this->uniquePositiveInts($row['value_ids'] ?? null, "attributes.{$index}.value_ids");
            $valueSet = [];

            foreach ($valueIds as $valueId) {
                $value = $globalValues->get($valueId);

                if ($value === null || $value->attribute_id !== $attributeId) {
                    throw ValidationException::withMessages([
                        "attributes.{$index}.value_ids" => __('Each selected value must belong to its attribute.'),
                    ]);
                }

                $valueSet[$valueId] = true;
            }

            $parsed[] = [
                'attribute_id' => $attributeId,
                'value_ids' => $valueIds,
                'value_set' => $valueSet,
            ];
        }

        return $parsed;
    }

    /**
     * @param  list<array{value_ids: list<int|string>, sku: string, price: string, compare_at_price?: string|null, quantity: int|string, is_default?: bool|int|string|null}>  $rows
     * @param  list<array{attribute_id: int, value_ids: list<int>, value_set: array<int, true>}>  $parsedAttributes
     * @param  Collection<int, AttributeValue>  $globalValues
     * @return array{default_key: string, items: array<string, array{key: string, map: array<int, int>, sku: string, price_minor: int, compare_at_minor: ?int, quantity: int, is_default: bool}>}
     */
    private function parseVariants(
        array $rows,
        array $parsedAttributes,
        Collection $globalValues,
        int $exponent,
    ): array {
        $assignedAttributeIds = array_map(fn (array $row): int => $row['attribute_id'], $parsedAttributes);
        $valueToAttribute = [];

        foreach ($parsedAttributes as $row) {
            foreach ($row['value_ids'] as $valueId) {
                $valueToAttribute[$valueId] = $row['attribute_id'];
            }
        }

        $items = [];
        $defaultKey = null;
        $seenSkus = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages([
                    "variants.{$index}" => __('Each variant is invalid.'),
                ]);
            }

            $valueIds = $this->uniquePositiveInts($row['value_ids'] ?? null, "variants.{$index}.value_ids");
            $map = [];

            foreach ($valueIds as $valueId) {
                $value = $globalValues->get($valueId);
                $attributeId = $valueToAttribute[$valueId] ?? $value?->attribute_id;

                if ($value === null || $attributeId === null || ! in_array($attributeId, $assignedAttributeIds, true)) {
                    throw ValidationException::withMessages([
                        "variants.{$index}.value_ids" => __('Each variant must use selected values from the assigned attributes.'),
                    ]);
                }

                if (isset($map[$attributeId])) {
                    throw ValidationException::withMessages([
                        "variants.{$index}.value_ids" => __('A variant may use at most one value per attribute.'),
                    ]);
                }

                $attributeValues = collect($parsedAttributes)->firstWhere('attribute_id', $attributeId);
                if ($attributeValues === null || ! isset($attributeValues['value_set'][$valueId])) {
                    throw ValidationException::withMessages([
                        "variants.{$index}.value_ids" => __('Each variant must use selected values from the assigned attributes.'),
                    ]);
                }

                $map[$attributeId] = $valueId;
            }

            if (count($map) !== count($assignedAttributeIds)) {
                throw ValidationException::withMessages([
                    "variants.{$index}.value_ids" => __('Each variant must include exactly one value for every assigned attribute.'),
                ]);
            }

            $key = CombinationKey::forVariable($map);

            if (isset($items[$key])) {
                throw ValidationException::withMessages([
                    "variants.{$index}" => __('Duplicate variant combinations are not allowed.'),
                ]);
            }

            $sku = VariantEconomics::normalizeSku((string) ($row['sku'] ?? ''));
            $skuField = "variants.{$index}.sku";

            if (isset($seenSkus[$sku])) {
                throw ValidationException::withMessages([
                    $skuField => __('This SKU is already used in your store.'),
                ]);
            }

            $seenSkus[$sku] = $key;

            $priceField = "variants.{$index}.price";
            $compareField = "variants.{$index}.compare_at_price";
            $quantityField = "variants.{$index}.quantity";

            try {
                $priceMinor = VariantEconomics::parsePrice((string) ($row['price'] ?? ''), $exponent, $priceField);
                $compareAtMinor = VariantEconomics::parseOptionalCompareAt(
                    isset($row['compare_at_price']) ? (string) $row['compare_at_price'] : null,
                    $exponent,
                    $priceMinor,
                    $compareField,
                );
                $quantity = VariantEconomics::normalizeQuantity($row['quantity'] ?? null, $quantityField);
            } catch (ValidationException $exception) {
                throw $exception;
            }

            $isDefault = $this->truthy($row['is_default'] ?? false);

            if ($isDefault) {
                if ($defaultKey !== null) {
                    throw ValidationException::withMessages([
                        'default_variant' => __('Exactly one default variant is required.'),
                    ]);
                }

                $defaultKey = $key;
            }

            $items[$key] = [
                'key' => $key,
                'map' => $map,
                'sku' => $sku,
                'price_minor' => $priceMinor,
                'compare_at_minor' => $compareAtMinor,
                'quantity' => $quantity,
                'is_default' => $isDefault,
            ];
        }

        if ($defaultKey === null) {
            throw ValidationException::withMessages([
                'default_variant' => __('Exactly one default variant is required.'),
            ]);
        }

        return [
            'default_key' => $defaultKey,
            'items' => $items,
        ];
    }

    /**
     * @param  list<array{attribute_id: int, value_ids: list<int>, value_set: array<int, true>}>  $parsedAttributes
     * @param  Collection<int, Attribute>  $globals
     * @param  Collection<int, AttributeValue>  $globalValues
     */
    private function syncDraftTopology(
        Product $product,
        array $parsedAttributes,
        Collection $globals,
        Collection $globalValues,
    ): void {
        $keepAttributeIds = [];

        foreach ($parsedAttributes as $position => $row) {
            $attribute = $globals->get($row['attribute_id']);
            $assignment = ProductAttribute::withTrashed()
                ->where('product_id', $product->id)
                ->where('attribute_id', $row['attribute_id'])
                ->first();

            if ($assignment === null) {
                if (! $attribute?->is_active) {
                    throw ValidationException::withMessages([
                        'attributes' => __('Inactive attributes cannot be assigned.'),
                    ]);
                }

                $assignment = ProductAttribute::query()->create([
                    'product_id' => $product->id,
                    'attribute_id' => $row['attribute_id'],
                    'position' => $position,
                ]);
            } else {
                if ($assignment->trashed()) {
                    if (! $attribute?->is_active) {
                        throw ValidationException::withMessages([
                            'attributes' => __('Inactive attributes cannot be assigned.'),
                        ]);
                    }

                    $assignment->restore();
                }

                $assignment->forceFill(['position' => $position])->save();
            }

            $keepAttributeIds[] = $assignment->id;
            $this->syncSelectedValues($product, $assignment, $row['value_ids'], $globalValues);
        }

        ProductAttribute::query()
            ->where('product_id', $product->id)
            ->whereNotIn('id', $keepAttributeIds)
            ->get()
            ->each(function (ProductAttribute $assignment): void {
                $assignment->selectedValues()->get()->each(fn (ProductAttributeValue $value) => $value->delete());
                $assignment->delete();
            });
    }

    /**
     * @param  list<int>  $valueIds
     * @param  Collection<int, AttributeValue>  $globalValues
     */
    private function syncSelectedValues(
        Product $product,
        ProductAttribute $assignment,
        array $valueIds,
        Collection $globalValues,
    ): void {
        $keepIds = [];

        foreach ($valueIds as $valueId) {
            $global = $globalValues->get($valueId);
            $selected = ProductAttributeValue::withTrashed()
                ->where('product_attribute_id', $assignment->id)
                ->where('attribute_value_id', $valueId)
                ->first();

            if ($selected === null) {
                if (! $global?->is_active) {
                    throw ValidationException::withMessages([
                        'attributes' => __('Inactive attribute values cannot be selected.'),
                    ]);
                }

                $selected = ProductAttributeValue::query()->create([
                    'product_id' => $product->id,
                    'product_attribute_id' => $assignment->id,
                    'attribute_id' => $assignment->attribute_id,
                    'attribute_value_id' => $valueId,
                ]);
            } elseif ($selected->trashed()) {
                if (! $global?->is_active) {
                    throw ValidationException::withMessages([
                        'attributes' => __('Inactive attribute values cannot be selected.'),
                    ]);
                }

                $selected->restore();
            }

            $keepIds[] = $selected->id;
        }

        ProductAttributeValue::query()
            ->where('product_attribute_id', $assignment->id)
            ->whereNotIn('id', $keepIds)
            ->get()
            ->each(fn (ProductAttributeValue $value) => $value->delete());
    }

    /**
     * @param  list<array{attribute_id: int, value_ids: list<int>, value_set: array<int, true>}>  $parsedAttributes
     */
    private function assertFrozenTopology(Product $product, array $parsedAttributes): void
    {
        $liveAssignments = ProductAttribute::query()
            ->where('product_id', $product->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        if ($liveAssignments->count() !== count($parsedAttributes)) {
            throw ValidationException::withMessages([
                'attributes' => __('Attribute topology is frozen after first publication.'),
            ]);
        }

        $requested = [];
        foreach ($parsedAttributes as $row) {
            $requested[$row['attribute_id']] = $row['value_ids'];
            sort($requested[$row['attribute_id']]);
        }

        foreach ($liveAssignments as $assignment) {
            if (! isset($requested[$assignment->attribute_id])) {
                throw ValidationException::withMessages([
                    'attributes' => __('Attribute topology is frozen after first publication.'),
                ]);
            }

            $liveValueIds = ProductAttributeValue::query()
                ->where('product_attribute_id', $assignment->id)
                ->orderBy('attribute_value_id')
                ->pluck('attribute_value_id')
                ->all();

            sort($liveValueIds);

            if ($liveValueIds !== $requested[$assignment->attribute_id]) {
                throw ValidationException::withMessages([
                    'attributes' => __('Selected attribute values are frozen after first publication.'),
                ]);
            }
        }
    }

    /**
     * @param  array{default_key: string, items: array<string, array{key: string, map: array<int, int>, sku: string, price_minor: int, compare_at_minor: ?int, quantity: int, is_default: bool}>}  $parsedVariants
     * @param  Collection<string, ProductVariant>  $existingVariants
     * @param  Collection<int, ProductAttribute>  $assignments
     * @param  Collection<int, Collection<int, ProductAttributeValue>>  $selectedValues
     * @param  Collection<int, AttributeValue>  $globalValues
     */
    private function syncVariants(
        Product $product,
        array $parsedVariants,
        Collection $existingVariants,
        Collection $assignments,
        Collection $selectedValues,
        Collection $globalValues,
        bool $structuralAllowed,
    ): void {
        $keepKeys = [];

        foreach ($parsedVariants['items'] as $key => $item) {
            $existing = $existingVariants->get($key);

            if ($existing === null) {
                if (! $structuralAllowed) {
                    throw ValidationException::withMessages([
                        'variants' => __('New variant combinations cannot be introduced after first publication.'),
                    ]);
                }

                $this->assertActiveGlobalsForCombination($item['map'], $assignments, $globalValues, creating: true);
                VariantEconomics::assertSkuAvailable($product->store_id, $item['sku']);

                $variant = ProductVariant::query()->create([
                    'product_id' => $product->id,
                    'store_id' => $product->store_id,
                    'sku' => $item['sku'],
                    'combination_key' => $key,
                    'price_amount_minor' => $item['price_minor'],
                    'compare_at_amount_minor' => $item['compare_at_minor'],
                    'quantity' => $item['quantity'],
                ]);

                $this->createVariantLinks($product, $variant, $item['map'], $assignments, $selectedValues);
                $keepKeys[] = $key;

                continue;
            }

            if ($existing->trashed()) {
                $this->assertActiveGlobalsForCombination($item['map'], $assignments, $globalValues, creating: false);
                VariantEconomics::assertSkuAvailable($product->store_id, $item['sku'], $existing->id);
                $existing->restore();
            } else {
                VariantEconomics::assertSkuAvailable($product->store_id, $item['sku'], $existing->id);
            }

            if ($existing->combination_key !== $key) {
                throw ValidationException::withMessages([
                    'variants' => __('A variant combination identity cannot be changed.'),
                ]);
            }

            $existing->fill([
                'sku' => $item['sku'],
                'store_id' => $product->store_id,
                'price_amount_minor' => $item['price_minor'],
                'compare_at_amount_minor' => $item['compare_at_minor'],
                'quantity' => $item['quantity'],
            ])->save();

            $keepKeys[] = $key;
        }

        $liveToArchive = ProductVariant::query()
            ->where('product_id', $product->id)
            ->whereNotIn('combination_key', $keepKeys)
            ->get();

        if ($liveToArchive->isNotEmpty() && ProductVariant::query()->where('product_id', $product->id)->whereIn('combination_key', $keepKeys)->count() < 1) {
            throw ValidationException::withMessages([
                'variants' => __('The last live variant cannot be archived.'),
            ]);
        }

        $liveToArchive->each(function (ProductVariant $variant): void {
            $variant->delete();
        });

        if (ProductVariant::query()->where('product_id', $product->id)->count() < 1) {
            throw ValidationException::withMessages([
                'variants' => __('The last live variant cannot be archived.'),
            ]);
        }
    }

    /**
     * @param  array<int, int>  $map
     * @param  Collection<int, ProductAttribute>  $assignments
     * @param  Collection<int, AttributeValue>  $globalValues
     */
    private function assertActiveGlobalsForCombination(
        array $map,
        Collection $assignments,
        Collection $globalValues,
        bool $creating,
    ): void {
        foreach ($map as $attributeId => $valueId) {
            $assignment = $assignments->get($attributeId);
            $attribute = $assignment?->attribute()->first() ?? Attribute::query()->find($attributeId);
            $value = $globalValues->get($valueId);

            if ($attribute === null || ! $attribute->is_active || $value === null || ! $value->is_active) {
                throw ValidationException::withMessages([
                    'variants' => $creating
                        ? __('Inactive attributes or values cannot be used for a new combination.')
                        : __('Inactive attributes or values cannot be used to restore a live combination.'),
                ]);
            }
        }
    }

    /**
     * @param  array<int, int>  $map
     * @param  Collection<int, ProductAttribute>  $assignments
     * @param  Collection<int, Collection<int, ProductAttributeValue>>  $selectedValues
     */
    private function createVariantLinks(
        Product $product,
        ProductVariant $variant,
        array $map,
        Collection $assignments,
        Collection $selectedValues,
    ): void {
        foreach ($map as $attributeId => $valueId) {
            $assignment = $assignments->get($attributeId);

            if ($assignment === null) {
                throw ValidationException::withMessages([
                    'variants' => __('Each variant must use selected values from the assigned attributes.'),
                ]);
            }

            $selected = $selectedValues
                ->get($assignment->id, collect())
                ->firstWhere('attribute_value_id', $valueId);

            if (! $selected instanceof ProductAttributeValue) {
                $selected = ProductAttributeValue::query()
                    ->where('product_attribute_id', $assignment->id)
                    ->where('attribute_value_id', $valueId)
                    ->first();
            }

            if (! $selected instanceof ProductAttributeValue) {
                throw ValidationException::withMessages([
                    'variants' => __('Unassigned attribute values cannot be linked to a variant.'),
                ]);
            }

            ProductVariantAttributeValue::query()->create([
                'variant_id' => $variant->id,
                'product_id' => $product->id,
                'product_attribute_id' => $assignment->id,
                'product_attribute_value_id' => $selected->id,
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private function uniquePositiveInts(mixed $values, string $field): array
    {
        if (! is_array($values)) {
            throw ValidationException::withMessages([
                $field => __('Select at least one value.'),
            ]);
        }

        $ids = [];

        foreach ($values as $value) {
            $id = $this->positiveInt($value, $field);

            if (isset($ids[$id])) {
                throw ValidationException::withMessages([
                    $field => __('Duplicate values are not allowed.'),
                ]);
            }

            $ids[$id] = $id;
        }

        return array_values($ids);
    }

    private function positiveInt(mixed $value, string $field): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && preg_match('/^[1-9]\d*$/', $value) === 1) {
            return (int) $value;
        }

        throw ValidationException::withMessages([
            $field => __('The selected value is invalid.'),
        ]);
    }

    private function truthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }
}
