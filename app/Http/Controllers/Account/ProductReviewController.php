<?php

namespace App\Http\Controllers\Account;

use App\Exceptions\ReviewException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreProductReviewRequest;
use App\Http\Requests\Account\UpdateProductReviewRequest;
use App\Models\Product;
use App\Models\ProductReview;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;

final class ProductReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviews,
    ) {}

    public function store(StoreProductReviewRequest $request, Product $product): RedirectResponse
    {
        try {
            $this->reviews->create(
                $request->user(),
                $product,
                $request->rating(),
                $request->body(),
            );
        } catch (ReviewException $e) {
            if ($e->errorCode === ReviewException::UNAUTHORIZED
                || $e->errorCode === ReviewException::NOT_FOUND
                || $e->errorCode === ReviewException::INELIGIBLE) {
                abort(404);
            }

            if ($e->errorCode === ReviewException::CONFLICT) {
                return back(fallback: route('storefront.product', $product->slug))
                    ->withErrors([
                        'review' => __('You have already reviewed this product.'),
                    ]);
            }

            if ($e->errorCode === ReviewException::INVALID) {
                return back(fallback: route('storefront.product', $product->slug))
                    ->withInput()
                    ->withErrors([
                        'review' => __('Please choose a rating from 1 to 5.'),
                    ]);
            }

            throw $e;
        }

        return back(fallback: route('storefront.product', $product->slug))
            ->with('status', __('Review submitted for moderation.'));
    }

    public function update(
        UpdateProductReviewRequest $request,
        ProductReview $productReview,
    ): RedirectResponse {
        $product = $productReview->product;

        try {
            $this->reviews->update(
                $request->user(),
                $productReview,
                $request->rating(),
                $request->body(),
            );
        } catch (ReviewException $e) {
            if ($e->errorCode === ReviewException::UNAUTHORIZED
                || $e->errorCode === ReviewException::NOT_FOUND
                || $e->errorCode === ReviewException::INELIGIBLE) {
                abort(404);
            }

            if ($e->errorCode === ReviewException::INVALID) {
                return back(fallback: route('storefront.product', $product->slug))
                    ->withInput()
                    ->withErrors([
                        'review' => __('Please choose a rating from 1 to 5.'),
                    ]);
            }

            throw $e;
        }

        return back(fallback: route('storefront.product', $product->slug))
            ->with('status', __('Review updated and awaiting moderation.'));
    }
}
