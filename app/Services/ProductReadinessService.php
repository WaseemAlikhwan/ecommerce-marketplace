<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Support\CombinationKey;
use App\Support\ProductReadiness\ReadinessIssue;
use App\Support\ProductReadiness\ReadinessIssueMessages;
use App\Support\ProductReadiness\ReadinessResult;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProductReadinessService
{
    public function evaluate(Product $product): ReadinessResult
    {
        $this->loadGraph($product);

        $integrity = [];
        $dependencies = [];
        $visibility = [];

        $this->appendTranslationIssues($product, $integrity);
        $this->appendCategoryIssues($product, $integrity, $dependencies, $visibility);
        $this->appendBrandIssues($product, $dependencies, $visibility);
        $this->appendCurrencyIssues($product, $dependencies, $visibility);
        $this->appendVendorStoreIssues($product, $dependencies, $visibility);
        $this->appendGalleryIssues($product, $integrity);
        $this->appendVariantIssues($product, $integrity);
        $this->appendTypeSpecificIssues($product, $integrity, $dependencies);

        return new ReadinessResult($integrity, $dependencies, $visibility);
    }

    public function assertIntegrityForPublished(Product $product): void
    {
        if ($product->status !== ProductStatus::Published) {
            return;
        }

        $result = $this->evaluate($product);

        if (! $result->hasIntegrityIssues()) {
            return;
        }

        throw ValidationException::withMessages(
            ReadinessIssueMessages::toValidationMessages($result->integrityIssues)
        );
    }

    public function loadGraph(Product $product): void
    {
        $product->unsetRelation('translations');
        $product->unsetRelation('images');
        $product->unsetRelation('primaryImage');
        $product->unsetRelation('variants');
        $product->unsetRelation('defaultVariant');
        $product->unsetRelation('productAttributes');
        $product->unsetRelation('category');
        $product->unsetRelation('brand');
        $product->unsetRelation('currency');
        $product->unsetRelation('store');

        $product->load([
            'translations',
            'images',
            'primaryImage',
            'variants.attributeValueLinks.productAttributeValue',
            'defaultVariant',
            'productAttributes.attribute',
            'productAttributes.selectedValues.attributeValue',
            'category.parent.parent',
            'brand',
            'currency',
            'store.vendor',
        ]);
    }

    /**
     * @param  list<ReadinessIssue>  $integrity
     */
    private function appendTranslationIssues(Product $product, array &$integrity): void
    {
        $translations = $product->translations;
        $ar = $translations->firstWhere('locale', 'ar');
        $en = $translations->firstWhere('locale', 'en');

        if ($ar === null || ! filled($ar->name)) {
            $integrity[] = new ReadinessIssue('missing_translation_ar', 'translations');
        }

        if ($en === null || ! filled($en->name)) {
            $integrity[] = new ReadinessIssue('missing_translation_en', 'translations');
        }
    }

    /**
     * @param  list<ReadinessIssue>  $integrity
     * @param  list<ReadinessIssue>  $dependencies
     * @param  list<ReadinessIssue>  $visibility
     */
    private function appendCategoryIssues(
        Product $product,
        array &$integrity,
        array &$dependencies,
        array &$visibility,
    ): void {
        if ($product->category_id === null) {
            $integrity[] = new ReadinessIssue('missing_category', 'category');

            return;
        }

        $category = $product->category;

        if ($category === null) {
            $integrity[] = new ReadinessIssue('missing_category', 'category');

            return;
        }

        if (! $category->isLeaf()) {
            $issue = new ReadinessIssue('category_not_leaf', 'category', [
                'category_id' => $category->id,
            ]);
            $dependencies[] = $issue;
            $visibility[] = $issue;
        }

        if (! $category->is_active) {
            $issue = new ReadinessIssue('inactive_category', 'category', [
                'category_id' => $category->id,
            ]);
            $dependencies[] = $issue;
            $visibility[] = $issue;
        } elseif (! $this->ancestorsAreActiveLoaded($category)) {
            $issue = new ReadinessIssue('inactive_category_ancestor', 'category', [
                'category_id' => $category->id,
            ]);
            $dependencies[] = $issue;
            $visibility[] = $issue;
        }
    }

    private function ancestorsAreActiveLoaded(Category $category): bool
    {
        $parent = $category->parent;

        while ($parent !== null) {
            if (! $parent->is_active) {
                return false;
            }

            $parent = $parent->parent;
        }

        return true;
    }

    /**
     * @param  list<ReadinessIssue>  $dependencies
     * @param  list<ReadinessIssue>  $visibility
     */
    private function appendBrandIssues(Product $product, array &$dependencies, array &$visibility): void
    {
        if ($product->brand_id === null) {
            return;
        }

        $brand = $product->brand;

        if ($brand === null || ! $brand->is_active) {
            $issue = new ReadinessIssue('inactive_brand', 'brand', [
                'brand_id' => $product->brand_id,
            ]);
            $dependencies[] = $issue;
            $visibility[] = $issue;
        }
    }

    /**
     * @param  list<ReadinessIssue>  $dependencies
     * @param  list<ReadinessIssue>  $visibility
     */
    private function appendCurrencyIssues(Product $product, array &$dependencies, array &$visibility): void
    {
        $currency = $product->currency;

        if ($currency === null || ! $currency->is_active) {
            $issue = new ReadinessIssue('inactive_currency', 'currency', [
                'currency_code' => $product->currency_code,
            ]);
            $dependencies[] = $issue;
            $visibility[] = $issue;
        }
    }

    /**
     * @param  list<ReadinessIssue>  $dependencies
     * @param  list<ReadinessIssue>  $visibility
     */
    private function appendVendorStoreIssues(Product $product, array &$dependencies, array &$visibility): void
    {
        $store = $product->store;
        $vendor = $store?->vendor;

        if ($vendor === null || $vendor->status !== VendorStatus::Approved) {
            $issue = new ReadinessIssue('vendor_not_approved', 'vendor');
            $dependencies[] = $issue;
            $visibility[] = $issue;
        }

        if ($store === null || ! $store->isSellable()) {
            $issue = new ReadinessIssue('store_not_sellable', 'store');
            $dependencies[] = $issue;
            $visibility[] = $issue;
        }
    }

    /**
     * @param  list<ReadinessIssue>  $integrity
     */
    private function appendGalleryIssues(Product $product, array &$integrity): void
    {
        $images = $product->images;

        if ($images->isEmpty()) {
            $integrity[] = new ReadinessIssue('missing_product_image', 'gallery');

            return;
        }

        if ($product->primary_image_id === null) {
            $integrity[] = new ReadinessIssue('missing_primary_image', 'gallery');

            return;
        }

        $primary = $images->firstWhere('id', (int) $product->primary_image_id);

        if ($primary === null) {
            $integrity[] = new ReadinessIssue('invalid_primary_image', 'gallery', [
                'primary_image_id' => (int) $product->primary_image_id,
            ]);
        }
    }

    /**
     * @param  list<ReadinessIssue>  $integrity
     */
    private function appendVariantIssues(Product $product, array &$integrity): void
    {
        $liveVariants = $product->variants;

        if ($liveVariants->isEmpty()) {
            $integrity[] = new ReadinessIssue('missing_live_variant', 'variants');

            return;
        }

        if ($product->default_variant_id === null) {
            $integrity[] = new ReadinessIssue('missing_default_variant', 'variants');
        } else {
            $default = $liveVariants->firstWhere('id', (int) $product->default_variant_id);

            if ($default === null) {
                $integrity[] = new ReadinessIssue('default_variant_not_live', 'variants', [
                    'variant_id' => (int) $product->default_variant_id,
                ]);
            }
        }

        foreach ($liveVariants as $variant) {
            $this->appendEconomicsIssues($variant, $integrity);
        }
    }

    /**
     * @param  list<ReadinessIssue>  $integrity
     */
    private function appendEconomicsIssues(ProductVariant $variant, array &$integrity): void
    {
        $sku = (string) $variant->sku;
        $normalized = strtoupper(trim($sku));

        if ($normalized === '' || $normalized !== $sku) {
            $integrity[] = new ReadinessIssue('invalid_sku', 'variants', [
                'variant_id' => $variant->id,
            ]);
        }

        if ((int) $variant->price_amount_minor <= 0) {
            $integrity[] = new ReadinessIssue('invalid_price', 'variants', [
                'variant_id' => $variant->id,
            ]);
        }

        if ($variant->compare_at_amount_minor !== null
            && (int) $variant->compare_at_amount_minor <= (int) $variant->price_amount_minor) {
            $integrity[] = new ReadinessIssue('invalid_compare_at_price', 'variants', [
                'variant_id' => $variant->id,
            ]);
        }

        if ((int) $variant->quantity < 0) {
            $integrity[] = new ReadinessIssue('invalid_quantity', 'variants', [
                'variant_id' => $variant->id,
            ]);
        }
    }

    /**
     * @param  list<ReadinessIssue>  $integrity
     * @param  list<ReadinessIssue>  $dependencies
     */
    private function appendTypeSpecificIssues(Product $product, array &$integrity, array &$dependencies): void
    {
        if ($product->type === ProductType::Simple) {
            $this->appendSimpleIssues($product, $integrity);

            return;
        }

        if ($product->type === ProductType::Variable) {
            $this->appendVariableIssues($product, $integrity, $dependencies);
        }
    }

    /**
     * @param  list<ReadinessIssue>  $integrity
     */
    private function appendSimpleIssues(Product $product, array &$integrity): void
    {
        $liveVariants = $product->variants;

        if ($liveVariants->count() !== 1) {
            $integrity[] = new ReadinessIssue('invalid_simple_variant_count', 'variants');
        }

        $variant = $liveVariants->first();

        if ($variant !== null && $variant->combination_key !== ProductVariant::DEFAULT_COMBINATION_KEY) {
            $integrity[] = new ReadinessIssue('invalid_simple_combination', 'variants', [
                'variant_id' => $variant->id,
            ]);
        }

        $hasAssignments = $product->productAttributes->isNotEmpty();
        $hasVariantLinks = $liveVariants->contains(
            fn (ProductVariant $live): bool => $live->attributeValueLinks->isNotEmpty()
        );

        if ($hasAssignments || $hasVariantLinks) {
            $integrity[] = new ReadinessIssue('invalid_simple_attributes', 'variants', [
                'has_assignments' => $hasAssignments,
                'has_variant_links' => $hasVariantLinks,
            ]);
        }
    }

    /**
     * @param  list<ReadinessIssue>  $integrity
     * @param  list<ReadinessIssue>  $dependencies
     */
    private function appendVariableIssues(Product $product, array &$integrity, array &$dependencies): void
    {
        /** @var Collection<int, ProductAttribute> $assignments */
        $assignments = $product->productAttributes->sortBy([
            ['position', 'asc'],
            ['id', 'asc'],
        ])->values();

        if ($assignments->isEmpty()) {
            $integrity[] = new ReadinessIssue('missing_variable_assignment', 'matrix');

            return;
        }

        if ($assignments->count() > ProductAttribute::MAX_PER_PRODUCT) {
            $integrity[] = new ReadinessIssue('matrix_assignment_limit_exceeded', 'matrix', [
                'count' => $assignments->count(),
                'max' => ProductAttribute::MAX_PER_PRODUCT,
            ]);
        }

        $requireActiveGlobals = $product->published_at === null;
        $assignmentAttributeIds = [];
        /** @var array<int, array{assignment_id: int, value_ids: array<int, true>}> $liveAssignmentSets */
        $liveAssignmentSets = [];
        $cartesian = 1;

        foreach ($assignments as $assignment) {
            $attributeId = (int) $assignment->attribute_id;
            $assignmentAttributeIds[] = $attributeId;

            $selectedValues = $assignment->selectedValues;
            $valueIds = [];

            foreach ($selectedValues as $selected) {
                $valueIds[(int) $selected->attribute_value_id] = true;
            }

            $liveAssignmentSets[$attributeId] = [
                'assignment_id' => (int) $assignment->id,
                'value_ids' => $valueIds,
            ];

            if ($selectedValues->isEmpty()) {
                $integrity[] = new ReadinessIssue('missing_assignment_values', 'matrix', [
                    'attribute_id' => $attributeId,
                    'product_attribute_id' => (int) $assignment->id,
                ]);
            }

            if ($selectedValues->count() > ProductAttributeValue::MAX_PER_ATTRIBUTE) {
                $integrity[] = new ReadinessIssue('matrix_value_limit_exceeded', 'matrix', [
                    'attribute_id' => $attributeId,
                    'count' => $selectedValues->count(),
                    'max' => ProductAttributeValue::MAX_PER_ATTRIBUTE,
                ]);
            }

            $cartesian *= max($selectedValues->count(), 0);

            if ($requireActiveGlobals && ($assignment->attribute === null || ! $assignment->attribute->is_active)) {
                $dependencies[] = new ReadinessIssue('inactive_first_publication_attribute', 'matrix', [
                    'attribute_id' => $attributeId,
                ]);
            }

            foreach ($selectedValues as $selected) {
                if ($requireActiveGlobals && ($selected->attributeValue === null || ! $selected->attributeValue->is_active)) {
                    $dependencies[] = new ReadinessIssue('inactive_first_publication_value', 'matrix', [
                        'attribute_id' => $attributeId,
                        'attribute_value_id' => (int) $selected->attribute_value_id,
                    ]);
                }
            }
        }

        if ($cartesian > ProductVariantMatrixService::MAX_CARTESIAN) {
            $integrity[] = new ReadinessIssue('matrix_cartesian_limit_exceeded', 'matrix', [
                'count' => $cartesian,
                'max' => ProductVariantMatrixService::MAX_CARTESIAN,
            ]);
        }

        if ($product->variants->count() > ProductVariant::MAX_LIVE_PER_PRODUCT) {
            $integrity[] = new ReadinessIssue('matrix_variant_limit_exceeded', 'matrix', [
                'count' => $product->variants->count(),
                'max' => ProductVariant::MAX_LIVE_PER_PRODUCT,
            ]);
        }

        foreach ($product->variants as $variant) {
            $this->appendVariableVariantCombinationIssues(
                $variant,
                $assignmentAttributeIds,
                $liveAssignmentSets,
                $integrity,
            );
        }
    }

    /**
     * @param  list<int>  $assignmentAttributeIds
     * @param  array<int, array{assignment_id: int, value_ids: array<int, true>}>  $liveAssignmentSets
     * @param  list<ReadinessIssue>  $integrity
     */
    private function appendVariableVariantCombinationIssues(
        ProductVariant $variant,
        array $assignmentAttributeIds,
        array $liveAssignmentSets,
        array &$integrity,
    ): void {
        $links = $variant->attributeValueLinks;
        $map = [];

        foreach ($links as $link) {
            $selected = $link->productAttributeValue;

            if ($selected === null) {
                $integrity[] = new ReadinessIssue('incomplete_variant_combination', 'matrix', [
                    'variant_id' => $variant->id,
                ]);

                return;
            }

            if ($selected->trashed()) {
                $integrity[] = new ReadinessIssue('soft_deleted_variant_attribute_value', 'matrix', [
                    'variant_id' => $variant->id,
                    'product_attribute_value_id' => (int) $selected->id,
                ]);

                return;
            }

            $attributeId = (int) $selected->attribute_id;
            $valueId = (int) $selected->attribute_value_id;
            $assignmentMeta = $liveAssignmentSets[$attributeId] ?? null;

            if ($assignmentMeta === null
                || (int) $link->product_attribute_id !== $assignmentMeta['assignment_id']
                || ! isset($assignmentMeta['value_ids'][$valueId])) {
                $integrity[] = new ReadinessIssue('orphan_variant_attribute_link', 'matrix', [
                    'variant_id' => $variant->id,
                    'attribute_id' => $attributeId,
                    'attribute_value_id' => $valueId,
                    'product_attribute_id' => (int) $link->product_attribute_id,
                    'product_attribute_value_id' => (int) $selected->id,
                ]);

                return;
            }

            if (isset($map[$attributeId])) {
                $integrity[] = new ReadinessIssue('incomplete_variant_combination', 'matrix', [
                    'variant_id' => $variant->id,
                    'attribute_id' => $attributeId,
                ]);

                return;
            }

            $map[$attributeId] = $valueId;
        }

        foreach ($assignmentAttributeIds as $attributeId) {
            if (! array_key_exists($attributeId, $map)) {
                $integrity[] = new ReadinessIssue('incomplete_variant_combination', 'matrix', [
                    'variant_id' => $variant->id,
                    'attribute_id' => $attributeId,
                ]);

                return;
            }
        }

        if (count($map) !== count($assignmentAttributeIds)) {
            $integrity[] = new ReadinessIssue('incomplete_variant_combination', 'matrix', [
                'variant_id' => $variant->id,
            ]);

            return;
        }

        try {
            $expected = CombinationKey::forVariable($map);
        } catch (\InvalidArgumentException) {
            $integrity[] = new ReadinessIssue('invalid_combination_key', 'matrix', [
                'variant_id' => $variant->id,
            ]);

            return;
        }

        if ($variant->combination_key !== $expected) {
            $integrity[] = new ReadinessIssue('invalid_combination_key', 'matrix', [
                'variant_id' => $variant->id,
            ]);
        }
    }
}
