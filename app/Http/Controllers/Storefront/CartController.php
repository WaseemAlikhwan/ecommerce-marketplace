<?php

namespace App\Http\Controllers\Storefront;

use App\Cart\CartMergeResult;
use App\Http\Controllers\Controller;
use App\Services\CartViewService;
use App\Services\Storefront\StorefrontNavigationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Storefront cart page (C1-D2). Presentation only — no Blade queries.
 */
final class CartController extends Controller
{
    public function __construct(
        private readonly CartViewService $cartViews,
        private readonly StorefrontNavigationService $navigation,
    ) {}

    public function show(Request $request): View
    {
        $locale = app()->getLocale();
        $cart = $this->cartViews->view($request->user(), $locale);
        $merge = $request->session()->pull(CartMergeResult::FLASH_KEY);

        return view('storefront.cart', [
            'cart' => $cart,
            'merge' => is_array($merge) ? $merge : null,
            'navCategories' => $this->navigation->get($locale),
        ]);
    }
}
