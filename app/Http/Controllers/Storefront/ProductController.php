<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\StorefrontNavigationService;
use App\Services\Storefront\StorefrontProductQuery;
use App\Services\WishlistService;
use App\Storefront\Presentation\ProductDetailPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ProductController extends Controller
{
    public function __construct(
        private readonly StorefrontProductQuery $products,
        private readonly StorefrontNavigationService $navigation,
        private readonly ProductDetailPresenter $presenter,
        private readonly WishlistService $wishlists,
    ) {}

    public function __invoke(Request $request, string $slug): View
    {
        $locale = app()->getLocale();
        $product = $this->products->findVisibleBySlugOrFail($slug);
        $user = $request->user();
        $wishlistItemId = $user !== null
            ? $this->wishlists->itemIdFor($user, $product)
            : null;

        return view('storefront.product', [
            'product' => $this->presenter->present($product, $locale)->toArray(),
            'navCategories' => $this->navigation->get($locale),
            'wishlistItemId' => $wishlistItemId,
        ]);
    }
}
