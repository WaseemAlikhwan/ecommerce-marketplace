<?php

namespace App\Services\Storefront;

use App\Models\Product;
use App\Models\Store;
use App\Storefront\Presentation\HomePagePresenter;
use App\Storefront\Presentation\ProductCardPresenter;
use App\Storefront\Presentation\StorePagePresenter;

/**
 * Bounded, non-paginated Home read orchestration.
 */
final class StorefrontHomeService
{
    public const PRODUCT_LIMIT = 8;

    public const STORE_LIMIT = 6;

    public function __construct(
        private readonly StorefrontProductQuery $products,
        private readonly ProductCardPresenter $cards,
        private readonly StorefrontNavigationService $navigation,
        private readonly StorePagePresenter $stores,
        private readonly HomePagePresenter $presenter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $resolved = $this->products->resolveCriteria([]);

        $productCards = $this->products
            ->browse($resolved, $locale)
            ->with($this->products->cardRelations())
            ->limit(self::PRODUCT_LIMIT)
            ->get()
            ->map(fn (Product $product): array => $this->cards->present($product, $locale)->toArray())
            ->values()
            ->all();

        $storeCards = Store::query()
            ->publiclyEligible()
            ->whereHas('products', fn ($query) => $query->storefrontVisible())
            ->withCount([
                'products as visible_products_count' => fn ($query) => $query->storefrontVisible(),
            ])
            ->orderByDesc('visible_products_count')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::STORE_LIMIT)
            ->get()
            ->map(fn (Store $store): array => $this->stores->present(
                $store,
                (int) $store->getAttribute('visible_products_count'),
            ))
            ->values()
            ->all();

        return $this->presenter->present(
            $this->navigation->get($locale),
            $productCards,
            $storeCards,
        );
    }
}
