<?php

namespace App\Support\ProductReadiness;

use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Services\ProductVariantMatrixService;

final class ReadinessIssueMessages
{
    /**
     * @param  list<ReadinessIssue>  $issues
     * @return array<string, list<string>>
     */
    public static function toValidationMessages(array $issues): array
    {
        $messages = [];

        foreach ($issues as $issue) {
            $field = self::fieldFor($issue);
            $messages[$field][] = self::messageFor($issue);
        }

        return $messages;
    }

    public static function messageFor(ReadinessIssue $issue): string
    {
        return match ($issue->code) {
            'missing_translation_ar' => __('Add an Arabic product name before publishing.'),
            'missing_translation_en' => __('Add an English product name before publishing.'),
            'missing_category' => __('Select a category before publishing.'),
            'category_not_leaf' => __('Select a leaf category before publishing.'),
            'inactive_category' => __('The selected category is inactive.'),
            'inactive_category_ancestor' => __('An ancestor of the selected category is inactive.'),
            'inactive_brand' => __('The selected brand is inactive.'),
            'inactive_currency' => __('The selected currency is inactive.'),
            'vendor_not_approved' => __('Your vendor account must be approved before publishing.'),
            'store_not_sellable' => __('Your store must be active before publishing.'),
            'missing_product_image' => __('Upload at least one product image before publishing.'),
            'missing_primary_image' => __('A primary product image is required.'),
            'invalid_primary_image' => __('The primary image must belong to this product.'),
            'missing_live_variant' => __('A product must keep at least one live variant.'),
            'missing_default_variant' => __('A default variant is required.'),
            'default_variant_not_live' => __('The default variant must be live.'),
            'invalid_simple_variant_count' => __('A simple product must have exactly one live variant.'),
            'invalid_simple_combination' => __('A simple product must use the default combination key.'),
            'invalid_simple_attributes' => __('A simple product cannot have attribute assignments or variant attribute links.'),
            'missing_variable_assignment' => __('Assign at least one attribute before publishing.'),
            'missing_assignment_values' => __('Each assigned attribute must have at least one selected value.'),
            'incomplete_variant_combination' => __('Each live variant must include exactly one value per assigned attribute.'),
            'invalid_combination_key' => __('A live variant has an invalid combination key.'),
            'soft_deleted_variant_attribute_value' => __('A live variant links to a removed attribute value selection.'),
            'orphan_variant_attribute_link' => __('A live variant links to an attribute value outside the current assignment set.'),
            'matrix_assignment_limit_exceeded' => __('A variable product may not exceed :max attribute assignments.', [
                'max' => $issue->meta['max'] ?? ProductAttribute::MAX_PER_PRODUCT,
            ]),
            'matrix_value_limit_exceeded' => __('An assigned attribute may not exceed :max selected values.', [
                'max' => $issue->meta['max'] ?? ProductAttributeValue::MAX_PER_ATTRIBUTE,
            ]),
            'matrix_cartesian_limit_exceeded' => __('The attribute combination count may not exceed :max.', [
                'max' => $issue->meta['max'] ?? ProductVariantMatrixService::MAX_CARTESIAN,
            ]),
            'matrix_variant_limit_exceeded' => __('A variable product may not exceed :max live variants.', [
                'max' => $issue->meta['max'] ?? ProductVariant::MAX_LIVE_PER_PRODUCT,
            ]),
            'inactive_first_publication_attribute' => __('An assigned attribute is inactive and cannot be used for first publication.'),
            'inactive_first_publication_value' => __('An assigned attribute value is inactive and cannot be used for first publication.'),
            'invalid_sku' => __('Every live variant needs a valid SKU.'),
            'invalid_price' => __('Every live variant needs a price greater than zero.'),
            'invalid_compare_at_price' => __('Compare-at price must be greater than the selling price.'),
            'invalid_quantity' => __('Quantity must be a whole number greater than or equal to zero.'),
            'product_not_ready' => __('This product is not ready to publish.'),
            default => __('This product is not ready to publish.'),
        };
    }

    private static function fieldFor(ReadinessIssue $issue): string
    {
        return match ($issue->section) {
            'translations' => 'translations',
            'category' => 'category_id',
            'brand' => 'brand_id',
            'currency' => 'currency_code',
            'vendor' => 'vendor',
            'store' => 'store',
            'gallery' => 'image',
            'variants' => isset($issue->meta['variant_id']) ? 'variants' : 'default_variant',
            'matrix' => 'attributes',
            default => 'publication',
        };
    }
}
