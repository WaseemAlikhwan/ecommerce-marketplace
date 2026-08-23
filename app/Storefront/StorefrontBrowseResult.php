<?php

namespace App\Storefront;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * HTTP-independent result of one sanitized Storefront browse request.
 *
 * @phpstan-type CardPayload array<string, mixed>
 */
final class StorefrontBrowseResult
{
    public readonly CatalogCriteriaResult $criteria;

    /** @var LengthAwarePaginator<int, CardPayload> */
    public readonly LengthAwarePaginator $paginator;

    /** @var LengthAwarePaginator<int, CardPayload> */
    public readonly LengthAwarePaginator $products;

    /** @var array<string, mixed> */
    public readonly array $queryParams;

    /**
     * @param  LengthAwarePaginator<int, CardPayload>  $paginator
     * @param  array<string, mixed>  $queryParameters
     */
    public function __construct(
        public readonly CatalogCriteriaResult $criteriaResult,
        LengthAwarePaginator $paginator,
        public readonly array $queryParameters,
    ) {
        $this->criteria = $this->criteriaResult;
        $this->paginator = $paginator;
        $this->products = $paginator;
        $this->queryParams = $this->queryParameters;
    }
}
