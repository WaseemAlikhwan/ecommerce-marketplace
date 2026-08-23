<?php

namespace App\Services\Storefront;

use App\Models\Product;
use App\Storefront\Presentation\ProductCardPresenter;
use App\Storefront\StorefrontBrowseResult;
use Illuminate\Http\Request;

/**
 * Sanitizes one public browse request and returns presentation-ready cards.
 */
final class StorefrontBrowseService
{
    public const PER_PAGE = 24;

    /**
     * Bounds public OFFSET work to at most 1,176 rows (page 50 at 24/page).
     */
    public const MAX_PUBLIC_PAGE = 50;

    public function __construct(
        private readonly StorefrontProductQuery $products,
        private readonly ProductCardPresenter $cards,
    ) {}

    /**
     * Path criteria are authoritative and override duplicate query-string keys.
     *
     * @param  Request|array<string, mixed>  $input
     * @param  array<string, mixed>  $pathCriteria
     * @param  list<string>  $omitFromLinks
     * @param  array<string, array{id: int, slug: string}>  $resolvedPath
     */
    public function browse(
        Request|array $input,
        array $pathCriteria = [],
        array $omitFromLinks = [],
        ?string $locale = null,
        array $resolvedPath = [],
    ): StorefrontBrowseResult {
        $requestInput = $input instanceof Request ? $input->query->all() : $input;
        $page = $this->sanitizePage($requestInput['page'] ?? null);
        unset($requestInput['page']);

        $normalizedInput = array_replace($requestInput, $pathCriteria);
        $resolved = $this->products->resolveCriteria($normalizedInput, $resolvedPath);

        $paginator = $this->products
            ->browse($resolved, $locale)
            ->with($this->products->cardRelations())
            ->paginate(self::PER_PAGE, ['*'], 'page', $page);

        $paginator->setCollection(
            $paginator->getCollection()
                ->map(fn (Product $product): array => $this->cards->present($product, $locale)->toArray())
                ->values(),
        );

        $queryParameters = $resolved->toQueryParameters($omitFromLinks);
        $paginator->appends($queryParameters);

        return new StorefrontBrowseResult(
            criteriaResult: $resolved,
            paginator: $paginator,
            queryParameters: $queryParameters,
        );
    }

    private function sanitizePage(mixed $page): int
    {
        if (is_int($page)) {
            return $page > 0 ? min($page, self::MAX_PUBLIC_PAGE) : 1;
        }

        if (! is_string($page) || ! preg_match('/^[1-9][0-9]*$/', $page)) {
            return 1;
        }

        $cap = (string) self::MAX_PUBLIC_PAGE;
        if (strlen($page) > strlen($cap) || (strlen($page) === strlen($cap) && $page > $cap)) {
            return self::MAX_PUBLIC_PAGE;
        }

        return (int) $page;
    }
}
