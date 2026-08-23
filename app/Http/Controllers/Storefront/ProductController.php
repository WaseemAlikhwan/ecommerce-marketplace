<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\StorefrontNavigationService;
use App\Services\Storefront\StorefrontProductQuery;
use App\Storefront\Presentation\ProductDetailPresenter;
use Illuminate\Contracts\View\View;

final class ProductController extends Controller
{
    public function __construct(
        private readonly StorefrontProductQuery $products,
        private readonly StorefrontNavigationService $navigation,
        private readonly ProductDetailPresenter $presenter,
    ) {}

    public function __invoke(string $slug): View
    {
        $locale = app()->getLocale();
        $product = $this->products->findVisibleBySlugOrFail($slug);

        return view('storefront.product', [
            'product' => $this->presenter->present($product, $locale)->toArray(),
            'navCategories' => $this->navigation->get($locale),
        ]);
    }
}
