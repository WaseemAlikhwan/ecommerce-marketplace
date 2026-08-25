<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\ProductReviewStatus;
use App\Http\Controllers\Controller;
use App\Services\ReviewService;
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
        private readonly ReviewService $reviews,
    ) {}

    public function __invoke(Request $request, string $slug): View
    {
        $locale = app()->getLocale();
        $product = $this->products->findVisibleBySlugOrFail($slug);
        $user = $request->user();
        $wishlistItemId = $user !== null
            ? $this->wishlists->itemIdFor($user, $product)
            : null;

        $approvedReviews = $this->reviews->listApprovedForProduct($product)
            ->map(static fn ($review): array => [
                'id' => (int) $review->id,
                'rating' => (int) $review->rating,
                'body' => $review->body,
            ])
            ->all();

        $reviewAggregate = $this->reviews->approvedAggregateForProduct($product);

        $ownReview = null;
        $canReview = false;
        if ($user !== null && $user->isCustomer()) {
            $canReview = $this->reviews->customerIsEligible($user, $product);
            $owned = $this->reviews->findForCustomerProduct($user, $product);
            if ($owned !== null) {
                $ownReview = [
                    'id' => (int) $owned->id,
                    'rating' => (int) $owned->rating,
                    'body' => $owned->body,
                    'status' => $owned->status->value,
                    'is_pending' => $owned->status === ProductReviewStatus::Pending,
                    'is_rejected' => $owned->status === ProductReviewStatus::Rejected,
                    'is_approved' => $owned->status === ProductReviewStatus::Approved,
                ];
            }
        }

        return view('storefront.product', [
            'product' => $this->presenter->present($product, $locale)->toArray(),
            'navCategories' => $this->navigation->get($locale),
            'wishlistItemId' => $wishlistItemId,
            'approvedReviews' => $approvedReviews,
            'reviewAggregate' => $reviewAggregate,
            'ownReview' => $ownReview,
            'canReview' => $canReview,
        ]);
    }
}
