<?php

namespace App\Http\Controllers\Storefront;

use App\Exceptions\CheckoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\PlaceCheckoutOrderRequest;
use App\Services\CheckoutReviewService;
use App\Services\CheckoutService;
use App\Services\OrderPlacementNotifier;
use App\Services\Storefront\StorefrontNavigationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutReviewService $reviews,
        private readonly CheckoutService $checkout,
        private readonly OrderPlacementNotifier $notifier,
        private readonly StorefrontNavigationService $navigation,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $locale = app()->getLocale();
        $review = $this->reviews->review($user, $locale);

        if (! $review->hasPayableLines) {
            return redirect()
                ->route('cart.show')
                ->withErrors(['cart' => __('Your cart has no payable items for checkout.')]);
        }

        return view('storefront.checkout', [
            'review' => $review,
            'navCategories' => $this->navigation->get($locale),
        ]);
    }

    public function store(PlaceCheckoutOrderRequest $request): RedirectResponse
    {
        $user = $request->user();
        $address = $request->resolveAddress();

        try {
            $result = $this->checkout->placeOrder($user, $address->fresh(['governorate', 'city']));
        } catch (CheckoutException $e) {
            return redirect()
                ->route('checkout.create')
                ->withInput()
                ->withErrors(['checkout' => $this->messageFor($e)]);
        }

        $this->notifier->notify($user, $result);

        return redirect()
            ->route('account.orders.show', $result->parentOrder)
            ->with('status', __('Order placed successfully.'));
    }

    private function messageFor(CheckoutException $e): string
    {
        return match ($e->errorCode) {
            CheckoutException::EMPTY_CART => __('Your cart is empty.'),
            CheckoutException::UNAVAILABLE_VARIANT => __('An item in your cart is no longer available.'),
            CheckoutException::INSUFFICIENT_STOCK => __('An item in your cart does not have enough stock.'),
            CheckoutException::INVALID_ADDRESS => __('Please choose a valid shipping address.'),
            CheckoutException::MIXED_CURRENCY_VENDOR => __('A vendor order cannot mix currencies.'),
            CheckoutException::COMMISSION_UNCONFIGURED => __('Checkout is temporarily unavailable. Please try again later.'),
            default => __('We could not place your order. Please try again.'),
        };
    }
}
