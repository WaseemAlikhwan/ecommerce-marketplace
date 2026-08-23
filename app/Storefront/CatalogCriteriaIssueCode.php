<?php

namespace App\Storefront;

/**
 * Machine-readable catalog criteria issue codes (no localized strings).
 */
final class CatalogCriteriaIssueCode
{
    public const Q_TRUNCATED = 'q_truncated';

    public const Q_MALFORMED = 'q_malformed';

    public const SORT_INVALID = 'sort_invalid';

    public const AVAILABILITY_INVALID = 'availability_invalid';

    public const CURRENCY_INVALID = 'currency_invalid';

    public const CURRENCY_MALFORMED = 'currency_malformed';

    public const CATEGORY_SLUG_INVALID = 'category_slug_invalid';

    public const BRAND_SLUG_INVALID = 'brand_slug_invalid';

    public const STORE_SLUG_INVALID = 'store_slug_invalid';

    public const PRICE_CURRENCY_REQUIRED = 'price_currency_required';

    public const PRICE_MIN_INVALID = 'price_min_invalid';

    public const PRICE_MAX_INVALID = 'price_max_invalid';

    public const PRICE_MIN_MALFORMED = 'price_min_malformed';

    public const PRICE_MAX_MALFORMED = 'price_max_malformed';

    public const PRICE_MIN_GT_MAX = 'price_min_gt_max';

    public const PRICE_SORT_CURRENCY_REQUIRED = 'price_sort_currency_required';

    public const ATTRS_MALFORMED = 'attrs_malformed';

    public const ATTRIBUTES_LIMIT_EXCEEDED = 'attributes_limit_exceeded';

    public const ATTRIBUTE_VALUES_LIMIT_EXCEEDED = 'attribute_values_limit_exceeded';

    public const ATTRIBUTE_CODE_INVALID = 'attribute_code_invalid';

    public const ATTRIBUTE_VALUE_CODE_INVALID = 'attribute_value_code_invalid';

    public const CATEGORY_UNRESOLVED = 'category_unresolved';

    public const BRAND_UNRESOLVED = 'brand_unresolved';

    public const STORE_UNRESOLVED = 'store_unresolved';

    public const CURRENCY_UNRESOLVED = 'currency_unresolved';

    public const ATTRIBUTE_INACTIVE_OR_UNKNOWN = 'attribute_inactive_or_unknown';

    public const ATTRIBUTE_VALUE_INACTIVE_OR_UNKNOWN = 'attribute_value_inactive_or_unknown';

    /**
     * Harmless normalization / fallback issues that do not zero the result set.
     *
     * @return list<string>
     */
    public static function nonBlockingCodes(): array
    {
        return [
            self::Q_TRUNCATED,
            self::Q_MALFORMED,
            self::SORT_INVALID,
            self::AVAILABILITY_INVALID,
            self::CURRENCY_MALFORMED,
            self::CURRENCY_INVALID,
            self::CATEGORY_SLUG_INVALID,
            self::BRAND_SLUG_INVALID,
            self::STORE_SLUG_INVALID,
            self::PRICE_CURRENCY_REQUIRED,
            self::PRICE_MIN_INVALID,
            self::PRICE_MAX_INVALID,
            self::PRICE_MIN_MALFORMED,
            self::PRICE_MAX_MALFORMED,
            self::PRICE_MIN_GT_MAX,
            self::PRICE_SORT_CURRENCY_REQUIRED,
            self::ATTRS_MALFORMED,
            self::ATTRIBUTES_LIMIT_EXCEEDED,
            self::ATTRIBUTE_VALUES_LIMIT_EXCEEDED,
            self::ATTRIBUTE_CODE_INVALID,
            self::ATTRIBUTE_VALUE_CODE_INVALID,
        ];
    }

    /**
     * Resolution failures that intentionally produce an empty result set.
     *
     * @return list<string>
     */
    public static function unresolvedFilterCodes(): array
    {
        return [
            self::CATEGORY_UNRESOLVED,
            self::BRAND_UNRESOLVED,
            self::STORE_UNRESOLVED,
            self::CURRENCY_UNRESOLVED,
            self::ATTRIBUTE_INACTIVE_OR_UNKNOWN,
            self::ATTRIBUTE_VALUE_INACTIVE_OR_UNKNOWN,
        ];
    }
}
