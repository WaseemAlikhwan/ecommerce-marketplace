<?php

namespace App\Storefront\Presentation;

use App\Storefront\CatalogCriteriaIssueCode;
use App\Storefront\CatalogCriteriaResult;
use App\Support\Locale;
use Illuminate\Support\Facades\Lang;

/**
 * Localizes public-safe catalog input issues without exposing resolved IDs.
 */
final class CatalogIssuePresenter
{
    /** @var array<string, string> */
    private const MESSAGES = [
        CatalogCriteriaIssueCode::Q_TRUNCATED => 'The search text was shortened to 80 characters.',
        CatalogCriteriaIssueCode::Q_MALFORMED => 'The search text format was not valid and was ignored.',
        CatalogCriteriaIssueCode::SORT_INVALID => 'The selected sort was not valid, so newest was used.',
        CatalogCriteriaIssueCode::AVAILABILITY_INVALID => 'The availability filter was not valid and was ignored.',
        CatalogCriteriaIssueCode::CURRENCY_INVALID => 'The currency code format was not valid and was ignored.',
        CatalogCriteriaIssueCode::CURRENCY_MALFORMED => 'The currency filter format was not valid and was ignored.',
        CatalogCriteriaIssueCode::CATEGORY_SLUG_INVALID => 'The category filter format was not valid and was ignored.',
        CatalogCriteriaIssueCode::BRAND_SLUG_INVALID => 'The brand filter format was not valid and was ignored.',
        CatalogCriteriaIssueCode::STORE_SLUG_INVALID => 'The store filter format was not valid and was ignored.',
        CatalogCriteriaIssueCode::PRICE_CURRENCY_REQUIRED => 'Choose a currency before filtering by price.',
        CatalogCriteriaIssueCode::PRICE_MIN_INVALID => 'The minimum price was not valid and was ignored.',
        CatalogCriteriaIssueCode::PRICE_MAX_INVALID => 'The maximum price was not valid and was ignored.',
        CatalogCriteriaIssueCode::PRICE_MIN_MALFORMED => 'The minimum price format was not valid and was ignored.',
        CatalogCriteriaIssueCode::PRICE_MAX_MALFORMED => 'The maximum price format was not valid and was ignored.',
        CatalogCriteriaIssueCode::PRICE_MIN_GT_MAX => 'The minimum price cannot be greater than the maximum price.',
        CatalogCriteriaIssueCode::PRICE_SORT_CURRENCY_REQUIRED => 'Choose a currency before sorting by price.',
        CatalogCriteriaIssueCode::ATTRS_MALFORMED => 'The attribute filters format was not valid and was ignored.',
        CatalogCriteriaIssueCode::ATTRIBUTES_LIMIT_EXCEEDED => 'Only the first three attribute filters were applied.',
        CatalogCriteriaIssueCode::ATTRIBUTE_VALUES_LIMIT_EXCEEDED => 'Only the first eight values for each attribute were applied.',
        CatalogCriteriaIssueCode::ATTRIBUTE_CODE_INVALID => 'An attribute filter code was not valid and was ignored.',
        CatalogCriteriaIssueCode::ATTRIBUTE_VALUE_CODE_INVALID => 'An attribute value code was not valid and was ignored.',
        CatalogCriteriaIssueCode::CATEGORY_UNRESOLVED => 'That category is not available.',
        CatalogCriteriaIssueCode::BRAND_UNRESOLVED => 'That brand is not available.',
        CatalogCriteriaIssueCode::STORE_UNRESOLVED => 'That store is not available.',
        CatalogCriteriaIssueCode::CURRENCY_UNRESOLVED => 'That currency is not available.',
        CatalogCriteriaIssueCode::ATTRIBUTE_INACTIVE_OR_UNKNOWN => 'An attribute filter is not available.',
        CatalogCriteriaIssueCode::ATTRIBUTE_VALUE_INACTIVE_OR_UNKNOWN => 'An attribute value filter is not available.',
    ];

    /**
     * A single code returns one row; a result/list returns a list of rows.
     *
     * @param  CatalogCriteriaResult|list<string>|string  $source
     * @return array<string, string>|list<array<string, string>>
     */
    public function present(CatalogCriteriaResult|array|string $source, ?string $locale = null): array
    {
        $single = is_string($source);
        $codes = match (true) {
            $source instanceof CatalogCriteriaResult => $source->allIssues(),
            is_array($source) => $source,
            default => [$source],
        };

        $rows = [];
        foreach (array_values(array_unique($codes)) as $code) {
            $rows[] = [
                'code' => $code,
                'kind' => in_array($code, CatalogCriteriaIssueCode::unresolvedFilterCodes(), true)
                    ? 'unresolved'
                    : 'malformed',
                'message' => $this->message($code, $locale),
            ];
        }

        return $single ? ($rows[0] ?? []) : $rows;
    }

    public function message(string $code, ?string $locale = null): string
    {
        $locale = Locale::sanitize($locale ?? app()->getLocale());
        $message = self::MESSAGES[$code] ?? 'Some filters could not be applied.';

        return Lang::get($message, [], $locale);
    }
}
