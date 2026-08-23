<?php

namespace App\Http\Controllers\Storefront;

use App\Cart\CartExceptionTranslator;
use App\Exceptions\CartException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StoreCartItemRequest;
use App\Http\Requests\Storefront\UpdateCartItemRequest;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;

/**
 * Storefront cart line mutations (C1-D1). No Blade cart UI here.
 */
final class CartItemController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
    ) {}

    public function store(StoreCartItemRequest $request): RedirectResponse
    {
        try {
            $result = $this->carts->add(
                $request->user(),
                $request->variantId(),
                $request->quantity(),
            );
        } catch (CartException $exception) {
            return $this->cartError($exception);
        }

        $status = $result->adjusted
            ? __('Quantity was adjusted to available stock.')
            : __('Item added to cart.');

        return back(fallback: route('cart.show'))->with('status', $status);
    }

    public function update(UpdateCartItemRequest $request, int $variant): RedirectResponse
    {
        try {
            $result = $this->carts->update(
                $request->user(),
                $variant,
                $request->quantity(),
            );
        } catch (CartException $exception) {
            return $this->cartError($exception);
        }

        if ($result->quantity === 0) {
            return back(fallback: route('cart.show'))->with('status', __('Item removed from cart.'));
        }

        $status = $result->adjusted
            ? __('Quantity was adjusted to available stock.')
            : __('Cart updated.');

        return back(fallback: route('cart.show'))->with('status', $status);
    }

    public function destroy(int $variant): RedirectResponse
    {
        $this->carts->remove(request()->user(), $variant);

        return back(fallback: route('cart.show'))->with('status', __('Item removed from cart.'));
    }

    private function cartError(CartException $exception): RedirectResponse
    {
        return back(fallback: route('cart.show'))
            ->withInput()
            ->withErrors([
                'cart' => CartExceptionTranslator::message($exception),
            ]);
    }
}
