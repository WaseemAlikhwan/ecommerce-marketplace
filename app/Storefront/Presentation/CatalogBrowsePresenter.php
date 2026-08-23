<?php

namespace App\Storefront\Presentation;

use App\Storefront\CatalogCriteriaResult;

/**
 * Converts resolved criteria into the array contract consumed by Blade.
 */
final class CatalogBrowsePresenter
{
    public function __construct(
        private readonly CatalogIssuePresenter $issues,
    ) {}

    /**
     * @param  list<string>  $omitFromQuery
     * @return array<string, mixed>
     */
    public function present(
        CatalogCriteriaResult $resolved,
        array $omitFromQuery = [],
        ?string $locale = null,
    ): array {
        $criteria = $resolved->effectiveCriteria();

        return [
            'criteria' => $criteria,
            'query' => $resolved->toQueryParameters($omitFromQuery),
            'issues' => $this->issues->present($resolved, $locale),
        ];
    }
}
