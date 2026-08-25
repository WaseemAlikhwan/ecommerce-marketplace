<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductReviewStatus;
use App\Exceptions\ReviewException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveProductReviewRequest;
use App\Http\Requests\Admin\RejectProductReviewRequest;
use App\Models\ProductReview;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviews,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProductReview::class);

        $status = $request->string('status')->toString();
        $reviews = ProductReview::query()
            ->with(['user', 'product'])
            ->when(
                in_array($status, array_column(ProductReviewStatus::cases(), 'value'), true),
                fn ($query) => $query->where('status', $status),
                fn ($query) => $query->where('status', ProductReviewStatus::Pending),
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.product-reviews.index', [
            'reviews' => $reviews,
            'status' => $status !== '' ? $status : ProductReviewStatus::Pending->value,
        ]);
    }

    public function show(ProductReview $productReview): View
    {
        $this->authorize('view', $productReview);

        return view('admin.product-reviews.show', [
            'review' => $productReview->load(['user', 'product']),
        ]);
    }

    public function approve(
        ApproveProductReviewRequest $request,
        ProductReview $productReview,
    ): RedirectResponse {
        try {
            $this->reviews->approve($request->user(), $productReview);
        } catch (ReviewException $exception) {
            if ($exception->errorCode === ReviewException::UNAUTHORIZED) {
                abort(403);
            }

            return back()->withErrors(['review' => __('Unable to update this review.')]);
        }

        return redirect()
            ->route('admin.reviews.show', $productReview)
            ->with('status', __('The review was approved.'));
    }

    public function reject(
        RejectProductReviewRequest $request,
        ProductReview $productReview,
    ): RedirectResponse {
        try {
            $this->reviews->reject($request->user(), $productReview);
        } catch (ReviewException $exception) {
            if ($exception->errorCode === ReviewException::UNAUTHORIZED) {
                abort(403);
            }

            return back()->withErrors(['review' => __('Unable to update this review.')]);
        }

        return redirect()
            ->route('admin.reviews.show', $productReview)
            ->with('status', __('The review was rejected.'));
    }
}
