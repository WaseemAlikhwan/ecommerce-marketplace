<?php

namespace App\Storefront;

use App\Support\AsciiSlug;

/**
 * Immutable, HTTP-independent catalog browse criteria.
 * Performs no database queries. Slug/code resolution belongs in the query service.
 * Hostile GET shapes are normalized without Array-to-string warnings or TypeErrors.
 */
final class CatalogCriteria
{
    public const MAX_Q_LENGTH = 80;

    public const MAX_SLUG_LENGTH = AsciiSlug::MAX_LENGTH;

    public const MAX_ATTRIBUTES = 3;

    public const MAX_VALUES_PER_ATTRIBUTE = 8;

    public const SLUG_PATTERN = AsciiSlug::PATTERN;

    /**
     * @param  array<string, list<string>>  $attributes  attribute code → value codes
     * @param  list<string>  $issues
     */
    private function __construct(
        public readonly ?string $q,
        public readonly ?string $categorySlug,
        public readonly ?string $brandSlug,
        public readonly ?string $storeSlug,
        public readonly ?string $currencyCode,
        public readonly ?string $minPriceInput,
        public readonly ?string $maxPriceInput,
        public readonly CatalogAvailability $availability,
        public readonly array $attributes,
        public readonly CatalogSort $sort,
        public readonly array $issues,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        $issues = [];

        $q = self::normalizeQ($input['q'] ?? null, $issues);
        $categorySlug = self::normalizeEntitySlug($input['category'] ?? null, CatalogCriteriaIssueCode::CATEGORY_SLUG_INVALID, $issues);
        $brandSlug = self::normalizeEntitySlug($input['brand'] ?? null, CatalogCriteriaIssueCode::BRAND_SLUG_INVALID, $issues);
        $storeSlug = self::normalizeEntitySlug($input['store'] ?? null, CatalogCriteriaIssueCode::STORE_SLUG_INVALID, $issues);

        $currencyCode = self::normalizeCurrencyCode($input['currency'] ?? null, $issues);

        $minPriceInput = self::normalizePriceInput($input['min_price'] ?? null, CatalogCriteriaIssueCode::PRICE_MIN_MALFORMED, $issues);
        $maxPriceInput = self::normalizePriceInput($input['max_price'] ?? null, CatalogCriteriaIssueCode::PRICE_MAX_MALFORMED, $issues);

        $availability = CatalogAvailability::Any;
        if (array_key_exists('availability', $input) && $input['availability'] !== null && $input['availability'] !== '') {
            if (! is_scalar($input['availability'])) {
                $issues[] = CatalogCriteriaIssueCode::AVAILABILITY_INVALID;
            } else {
                $parsedAvailability = CatalogAvailability::tryFromInput((string) $input['availability']);
                if ($parsedAvailability === null) {
                    $issues[] = CatalogCriteriaIssueCode::AVAILABILITY_INVALID;
                } else {
                    $availability = $parsedAvailability;
                }
            }
        }

        $sort = CatalogSort::Newest;
        if (array_key_exists('sort', $input) && $input['sort'] !== null && $input['sort'] !== '') {
            if (! is_scalar($input['sort'])) {
                $issues[] = CatalogCriteriaIssueCode::SORT_INVALID;
            } else {
                $parsedSort = CatalogSort::tryFromInput((string) $input['sort']);
                if ($parsedSort === null) {
                    $issues[] = CatalogCriteriaIssueCode::SORT_INVALID;
                } else {
                    $sort = $parsedSort;
                }
            }
        }

        $attributes = self::normalizeAttributes($input['attrs'] ?? $input['attributes'] ?? null, $issues);

        $needsCurrencyForPrice = $minPriceInput !== null || $maxPriceInput !== null;
        if ($needsCurrencyForPrice && $currencyCode === null) {
            $issues[] = CatalogCriteriaIssueCode::PRICE_CURRENCY_REQUIRED;
            $minPriceInput = null;
            $maxPriceInput = null;
        }

        if (in_array($sort, [CatalogSort::PriceAsc, CatalogSort::PriceDesc], true) && $currencyCode === null) {
            $issues[] = CatalogCriteriaIssueCode::PRICE_SORT_CURRENCY_REQUIRED;
            $sort = CatalogSort::Newest;
        }

        return new self(
            q: $q,
            categorySlug: $categorySlug,
            brandSlug: $brandSlug,
            storeSlug: $storeSlug,
            currencyCode: $currencyCode,
            minPriceInput: $minPriceInput,
            maxPriceInput: $maxPriceInput,
            availability: $availability,
            attributes: $attributes,
            sort: $sort,
            issues: array_values(array_unique($issues)),
        );
    }

    public function hasIssues(): bool
    {
        return $this->issues !== [];
    }

    /**
     * Canonical query parameters preserved in browse pagination links.
     *
     * @return array<string, mixed>
     */
    public function toQueryParameters(array $omit = []): array
    {
        $params = [];

        if ($this->q !== null) {
            $params['q'] = $this->q;
        }
        if ($this->categorySlug !== null && ! in_array('category', $omit, true)) {
            $params['category'] = $this->categorySlug;
        }
        if ($this->brandSlug !== null && ! in_array('brand', $omit, true)) {
            $params['brand'] = $this->brandSlug;
        }
        if ($this->storeSlug !== null && ! in_array('store', $omit, true)) {
            $params['store'] = $this->storeSlug;
        }
        if ($this->currencyCode !== null) {
            $params['currency'] = $this->currencyCode;
        }
        if ($this->minPriceInput !== null) {
            $params['min_price'] = $this->minPriceInput;
        }
        if ($this->maxPriceInput !== null) {
            $params['max_price'] = $this->maxPriceInput;
        }
        if ($this->availability !== CatalogAvailability::Any) {
            $params['availability'] = $this->availability->value;
        }
        if ($this->attributes !== []) {
            $params['attrs'] = $this->attributes;
        }
        if ($this->sort !== CatalogSort::Newest) {
            $params['sort'] = $this->sort->value;
        }

        return $params;
    }

    /**
     * @param  list<string>  $issues
     */
    private static function normalizeQ(mixed $raw, array &$issues): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (! is_scalar($raw)) {
            $issues[] = CatalogCriteriaIssueCode::Q_MALFORMED;

            return null;
        }

        $q = trim((string) $raw);
        if ($q === '') {
            return null;
        }

        if (mb_strlen($q) > self::MAX_Q_LENGTH) {
            $issues[] = CatalogCriteriaIssueCode::Q_TRUNCATED;
            $q = mb_substr($q, 0, self::MAX_Q_LENGTH);
        }

        return $q;
    }

    /**
     * @param  list<string>  $issues
     */
    private static function normalizeEntitySlug(mixed $raw, string $invalidCode, array &$issues): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (! is_scalar($raw)) {
            $issues[] = $invalidCode;

            return null;
        }

        $slug = strtolower(trim((string) $raw));
        if ($slug === '') {
            return null;
        }

        if (! AsciiSlug::isValid($slug, self::MAX_SLUG_LENGTH)) {
            $issues[] = $invalidCode;

            return null;
        }

        return $slug;
    }

    /**
     * @param  list<string>  $issues
     */
    private static function normalizeCurrencyCode(mixed $raw, array &$issues): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (! is_scalar($raw)) {
            $issues[] = CatalogCriteriaIssueCode::CURRENCY_MALFORMED;

            return null;
        }

        $currencyCode = strtoupper(trim((string) $raw));
        if ($currencyCode === '') {
            return null;
        }

        if (! preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            $issues[] = CatalogCriteriaIssueCode::CURRENCY_INVALID;

            return null;
        }

        return $currencyCode;
    }

    /**
     * @param  list<string>  $issues
     */
    private static function normalizePriceInput(mixed $raw, string $malformedCode, array &$issues): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (! is_scalar($raw)) {
            $issues[] = $malformedCode;

            return null;
        }

        $value = trim((string) $raw);

        return $value !== '' ? $value : null;
    }

    /**
     * Bound raw Attribute/Value inspection before validation (deterministic first N).
     *
     * @param  list<string>  $issues
     * @return array<string, list<string>>
     */
    private static function normalizeAttributes(mixed $raw, array &$issues): array
    {
        if ($raw === null) {
            return [];
        }

        if (! is_array($raw)) {
            $issues[] = CatalogCriteriaIssueCode::ATTRS_MALFORMED;

            return [];
        }

        if (count($raw) > self::MAX_ATTRIBUTES) {
            $issues[] = CatalogCriteriaIssueCode::ATTRIBUTES_LIMIT_EXCEEDED;
            $raw = array_slice($raw, 0, self::MAX_ATTRIBUTES, true);
        }

        $normalized = [];
        $valuesLimitReported = false;

        foreach ($raw as $code => $values) {
            if (! is_string($code) && ! is_int($code)) {
                $issues[] = CatalogCriteriaIssueCode::ATTRIBUTE_CODE_INVALID;

                continue;
            }

            $attributeCode = strtolower(trim((string) $code));
            if (! AsciiSlug::isValid($attributeCode, self::MAX_SLUG_LENGTH)) {
                $issues[] = CatalogCriteriaIssueCode::ATTRIBUTE_CODE_INVALID;

                continue;
            }

            if (! is_array($values)) {
                if (is_scalar($values)) {
                    $values = [$values];
                } else {
                    $issues[] = CatalogCriteriaIssueCode::ATTRIBUTE_VALUE_CODE_INVALID;

                    continue;
                }
            }

            if (count($values) > self::MAX_VALUES_PER_ATTRIBUTE) {
                if (! $valuesLimitReported) {
                    $issues[] = CatalogCriteriaIssueCode::ATTRIBUTE_VALUES_LIMIT_EXCEEDED;
                    $valuesLimitReported = true;
                }
                $values = array_slice($values, 0, self::MAX_VALUES_PER_ATTRIBUTE);
            }

            $valueCodes = [];
            foreach ($values as $value) {
                if (! is_scalar($value)) {
                    $issues[] = CatalogCriteriaIssueCode::ATTRIBUTE_VALUE_CODE_INVALID;

                    continue;
                }

                $valueCode = strtolower(trim((string) $value));
                if (! AsciiSlug::isValid($valueCode, self::MAX_SLUG_LENGTH)) {
                    $issues[] = CatalogCriteriaIssueCode::ATTRIBUTE_VALUE_CODE_INVALID;

                    continue;
                }

                if (! in_array($valueCode, $valueCodes, true)) {
                    $valueCodes[] = $valueCode;
                }
            }

            if ($valueCodes === []) {
                continue;
            }

            $normalized[$attributeCode] = $valueCodes;
        }

        return $normalized;
    }
}
