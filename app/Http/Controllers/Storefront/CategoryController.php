<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Storefront\StorefrontBrowseService;
use App\Services\Storefront\StorefrontFilterOptionsService;
use App\Storefront\Presentation\CatalogBrowsePresenter;
use App\Storefront\Presentation\CategoryPagePresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class CategoryController extends Controller
{
    public function __construct(
        private readonly StorefrontBrowseService $browse,
        private readonly StorefrontFilterOptionsService $filters,
        private readonly CatalogBrowsePresenter $catalogPresenter,
        private readonly CategoryPagePresenter $categoryPresenter,
    ) {}

    public function __invoke(Request $request, string $slug): View
    {
        $locale = app()->getLocale();
        $category = Category::query()
            ->storefrontNavigable()
            ->where('slug', $slug)
            ->with([
                'translations',
                'parent.translations',
                'parent.parent.translations',
                'children' => fn ($query) => $query
                    ->storefrontNavigable()
                    ->with('translations'),
            ])
            ->firstOrFail();

        $browse = $this->browse->browse(
            input: $request,
            pathCriteria: ['category' => (string) $category->slug],
            omitFromLinks: ['category'],
            locale: $locale,
            resolvedPath: [
                'category' => [
                    'id' => (int) $category->id,
                    'slug' => (string) $category->slug,
                ],
            ],
        );
        $filterOptions = $this->filters->get($locale);

        return view('storefront.category', [
            'category' => $this->categoryPresenter->present($category, $locale),
            'products' => $browse->products,
            'catalog' => $this->catalogPresenter->present($browse->criteriaResult, ['category'], $locale),
            'filters' => $filterOptions,
            'navCategories' => $filterOptions['navigation'],
        ]);
    }
}
