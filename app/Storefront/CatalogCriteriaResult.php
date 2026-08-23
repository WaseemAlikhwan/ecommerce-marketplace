<?php

namespace App\Storefront;

use App\Support\ModelFreePayload;
use Illuminate\Support\Collection;

/**
 * Query-layer result wrapping applied criteria, machine-readable issues,
 * and optional resolved entity identifiers (no localized messages).
 *
 * Presentation payloads (Product cards) must be passed as arrays/DTOs — never Eloquent models.
 */
final class CatalogCriteriaResult
{
    /**
     * @param  list<string>  $issues
     * @param  array<string, list<int>>  $resolvedAttributeValueIds  attribute_id → attribute_value ids
     */
    public function __construct(
        public readonly CatalogCriteria $criteria,
        public readonly array $issues,
        public readonly ?int $categoryId = null,
        public readonly ?int $brandId = null,
        public readonly ?int $storeId = null,
        public readonly ?string $currencyCode = null,
        public readonly ?int $currencyExponent = null,
        public readonly ?int $minPriceMinor = null,
        public readonly ?int $maxPriceMinor = null,
        public readonly array $resolvedAttributeValueIds = [],
        public readonly bool $categoryUnresolved = false,
        public readonly bool $brandUnresolved = false,
        public readonly bool $storeUnresolved = false,
        public readonly bool $currencyUnresolved = false,
        public readonly bool $attributesUnresolved = false,
    ) {}

    /**
     * Every normalization and resolution issue (deduplicated).
     *
     * @return list<string>
     */
    public function allIssues(): array
    {
        return array_values(array_unique([...$this->criteria->issues, ...$this->issues]));
    }

    public function effectiveMinPriceInput(): ?string
    {
        return $this->priceBoundWasRejected('min')
            ? null
            : $this->criteria->minPriceInput;
    }

    public function effectiveMaxPriceInput(): ?string
    {
        return $this->priceBoundWasRejected('max')
            ? null
            : $this->criteria->maxPriceInput;
    }

    /**
     * Normalized criteria after semantic resolution/fallback.
     *
     * Valid-looking unresolved entity filters intentionally remain present so
     * the user can see and remove the filter that caused a fail-closed result.
     *
     * @return array<string, mixed>
     */
    public function effectiveCriteria(): array
    {
        return [
            'q' => $this->criteria->q,
            'category' => $this->criteria->categorySlug,
            'brand' => $this->criteria->brandSlug,
            'store' => $this->criteria->storeSlug,
            'currency' => $this->criteria->currencyCode,
            'min_price' => $this->effectiveMinPriceInput(),
            'max_price' => $this->effectiveMaxPriceInput(),
            'availability' => $this->criteria->availability->value,
            'attrs' => $this->criteria->attributes,
            'sort' => $this->criteria->sort->value,
        ];
    }

    /**
     * Canonical effective parameters for forms, chips, and pagination.
     *
     * @param  list<string>  $omit
     * @return array<string, mixed>
     */
    public function toQueryParameters(array $omit = []): array
    {
        $parameters = $this->criteria->toQueryParameters($omit);

        if ($this->effectiveMinPriceInput() === null) {
            unset($parameters['min_price']);
        }
        if ($this->effectiveMaxPriceInput() === null) {
            unset($parameters['max_price']);
        }

        if ($this->criteria->currencyCode === null) {
            unset($parameters['currency'], $parameters['min_price'], $parameters['max_price']);
            if (in_array($parameters['sort'] ?? null, [
                CatalogSort::PriceAsc->value,
                CatalogSort::PriceDesc->value,
            ], true)) {
                unset($parameters['sort']);
            }
        }

        return $parameters;
    }

    /**
     * True when a recognized filter was syntactically present but could not be
     * resolved to an active entity — browse intentionally returns zero rows.
     */
    public function hasUnresolvedFilters(): bool
    {
        if ($this->categoryUnresolved
            || $this->brandUnresolved
            || $this->storeUnresolved
            || $this->currencyUnresolved
            || $this->attributesUnresolved
        ) {
            return true;
        }

        if ($this->criteria->attributes !== [] && $this->resolvedAttributeValueIds === []) {
            return true;
        }

        foreach ($this->allIssues() as $code) {
            if (in_array($code, CatalogCriteriaIssueCode::unresolvedFilterCodes(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @deprecated Prefer hasUnresolvedFilters() for fail-closed browse gates.
     */
    public function hasBlockingIssues(): bool
    {
        return $this->hasUnresolvedFilters();
    }

    /**
     * @param  Collection<int, mixed>|array<int, mixed>  $items
     * @return array{criteria: array<string, mixed>, issues: list<string>, resolved: array<string, mixed>, items: mixed}
     */
    public function toArray(Collection|array $items = []): array
    {
        ModelFreePayload::assert($items, 'CatalogCriteriaResult::toArray()');
        $normalizedItems = $items instanceof Collection ? $items->all() : $items;
        $effective = $this->effectiveCriteria();

        return [
            'criteria' => [
                'q' => $effective['q'],
                'category_slug' => $effective['category'],
                'brand_slug' => $effective['brand'],
                'store_slug' => $effective['store'],
                'currency_code' => $this->currencyCode ?? $this->criteria->currencyCode,
                'min_price_minor' => $this->minPriceMinor === null ? null : (string) $this->minPriceMinor,
                'max_price_minor' => $this->maxPriceMinor === null ? null : (string) $this->maxPriceMinor,
                'availability' => $effective['availability'],
                'attributes' => $effective['attrs'],
                'sort' => $effective['sort'],
            ],
            'issues' => $this->allIssues(),
            'resolved' => [
                'category_id' => $this->categoryId,
                'brand_id' => $this->brandId,
                'store_id' => $this->storeId,
                'currency_code' => $this->currencyCode,
                'currency_exponent' => $this->currencyExponent,
                'attribute_value_ids' => $this->resolvedAttributeValueIds,
            ],
            'items' => $normalizedItems,
        ];
    }

    private function priceBoundWasRejected(string $bound): bool
    {
        $codes = $bound === 'min'
            ? [
                CatalogCriteriaIssueCode::PRICE_MIN_INVALID,
                CatalogCriteriaIssueCode::PRICE_MIN_MALFORMED,
                CatalogCriteriaIssueCode::PRICE_MIN_GT_MAX,
                CatalogCriteriaIssueCode::PRICE_CURRENCY_REQUIRED,
            ]
            : [
                CatalogCriteriaIssueCode::PRICE_MAX_INVALID,
                CatalogCriteriaIssueCode::PRICE_MAX_MALFORMED,
                CatalogCriteriaIssueCode::PRICE_MIN_GT_MAX,
                CatalogCriteriaIssueCode::PRICE_CURRENCY_REQUIRED,
            ];

        return array_intersect($codes, $this->allIssues()) !== [];
    }
}
