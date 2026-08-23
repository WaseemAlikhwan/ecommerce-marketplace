<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\StorefrontBrowseService;
use App\Services\Storefront\StorefrontFilterOptionsService;
use App\Storefront\Presentation\CatalogBrowsePresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class SearchController extends Controller
{
    public function __construct(
        private readonly StorefrontBrowseService $browse,
        private readonly StorefrontFilterOptionsService $filters,
        private readonly CatalogBrowsePresenter $presenter,
    ) {}

    public function __invoke(Request $request): View
    {
        $locale = app()->getLocale();
        $browse = $this->browse->browse($request, locale: $locale);
        $filterOptions = $this->filters->get($locale);

        return view('storefront.search', [
            'products' => $browse->products,
            'catalog' => $this->presenter->present($browse->criteriaResult, locale: $locale),
            'filters' => $filterOptions,
            'navCategories' => $filterOptions['navigation'],
        ]);
    }
}
