<?php

namespace App\Http\Controllers\Storefront;

use App\Coupons\CheckoutCouponSession;
use App\Coupons\CouponLineCandidate;
use App\Exceptions\CheckoutException;
use App\Exceptions\CouponException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ApplyCheckoutCouponRequest;
use App\Http\Requests\Storefront\PlaceCheckoutOrderRequest;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartViewService;
use App\Services\CheckoutReviewService;
use App\Services\CheckoutService;
use App\Services\CouponService;
use App\Services\OrderPlacementNotifier;
use App\Services\Storefront\StorefrontNavigationService;
use App\Support\CouponMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutReviewService $reviews,
        private readonly CheckoutService $checkout,
        private readonly CouponService $coupons,
        private readonly CartViewService $cartViews,
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

    public function applyCoupon(ApplyCheckoutCouponRequest $request): RedirectResponse
    {
        $user = $request->user();
        $code = (string) $request->validated('code');
        $candidates = $this->couponCandidatesForUser($user);

        try {
            $this->coupons->assertSingleCodeAllowed(CheckoutCouponSession::get(), $code);
            $quote = $this->coupons->validateAndQuote(
                $user,
                $code,
                $candidates,
                CheckoutCouponSession::get(),
            );
        } catch (CouponException $e) {
            return redirect()
                ->route('checkout.create')
                ->withInput()
                ->withErrors(['coupon' => CouponMessage::forErrorCode($e->errorCode)]);
        }

        CheckoutCouponSession::put($quote->code);

        return redirect()
            ->route('checkout.create')
            ->with('status', __('Coupon applied.'));
    }

    public function removeCoupon(Request $request): RedirectResponse
    {
        if ($request->user() === null) {
            return redirect()->route('login');
        }

        CheckoutCouponSession::forget();

        return redirect()
            ->route('checkout.create')
            ->with('status', __('Coupon removed.'));
    }

    private function messageFor(CheckoutException $e): string
    {
        if ($e->errorCode === CheckoutException::COUPON_REJECTED && $e->couponErrorCode !== null) {
            return CouponMessage::forErrorCode($e->couponErrorCode);
        }

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

    /**
     * @return list<CouponLineCandidate>
     */
    private function couponCandidatesForUser(User $user): array
    {
        $locale = app()->getLocale();
        $cart = $this->cartViews->view($user, $locale);
        $payable = array_values(array_filter(
            $cart->lines,
            static fn ($line): bool => $line->contributesToTotals(),
        ));

        if ($payable === []) {
            return [];
        }

        $variantIds = array_map(static fn ($line): int => $line->variantId, $payable);
        $variants = ProductVariant::query()
            ->with(['product.store.vendor'])
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        $candidates = [];
        foreach ($payable as $line) {
            $variant = $variants->get($line->variantId);
            $product = $variant?->product;
            $vendorId = (int) ($product?->store?->vendor_id ?? $product?->store?->vendor?->id ?? 0);
            if ($product === null || $vendorId <= 0) {
                continue;
            }

            $candidates[] = new CouponLineCandidate(
                (int) $product->id,
                $vendorId,
                $product->category_id !== null ? (int) $product->category_id : null,
                $line->currencyCode,
                (int) ($line->lineTotal['amount_minor'] ?? 0),
            );
        }

        return $candidates;
    }
}
