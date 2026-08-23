<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Storefront\StorefrontBrowseService;
use App\Services\Storefront\StorefrontFilterOptionsService;
use App\Storefront\Presentation\CatalogBrowsePresenter;
use App\Storefront\Presentation\StorePagePresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class StoreController extends Controller
{
    public function __construct(
        private readonly StorefrontBrowseService $browse,
        private readonly StorefrontFilterOptionsService $filters,
        private readonly CatalogBrowsePresenter $catalogPresenter,
        private readonly StorePagePresenter $storePresenter,
    ) {}

    public function __invoke(Request $request, string $slug): View
    {
        $locale = app()->getLocale();
        $store = Store::query()
            ->publiclyEligible()
            ->where('slug', $slug)
            ->withCount([
                'products as visible_products_count' => fn ($query) => $query->storefrontVisible(),
            ])
            ->firstOrFail();

        $browse = $this->browse->browse(
            input: $request,
            pathCriteria: ['store' => (string) $store->slug],
            omitFromLinks: ['store'],
            locale: $locale,
            resolvedPath: [
                'store' => [
                    'id' => (int) $store->id,
                    'slug' => (string) $store->slug,
                ],
            ],
        );
        $filterOptions = $this->filters->get($locale);

        return view('storefront.store', [
            'store' => $this->storePresenter->present(
                $store,
                (int) $store->getAttribute('visible_products_count'),
            ),
            'products' => $browse->products,
            'catalog' => $this->catalogPresenter->present($browse->criteriaResult, ['store'], $locale),
            'filters' => $filterOptions,
            'navCategories' => $filterOptions['navigation'],
        ]);
    }
}
