<?php

namespace App\Http\Controllers\Account;

use App\Exceptions\OrderCancellationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\CancelParentOrderRequest;
use App\Models\ParentOrder;
use App\Services\OrderCancellationService;
use App\Services\OrderViewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ParentOrderController extends Controller
{
    public function __construct(
        private readonly OrderViewService $orderViews,
        private readonly OrderCancellationService $cancellations,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ParentOrder::class);

        $user = $request->user();
        $orders = ParentOrder::query()
            ->where('user_id', $user->id)
            ->with(['vendorOrders.payment'])
            ->latest('placed_at')
            ->latest('id')
            ->paginate(15);

        $rows = $this->orderViews->parentIndexRows(
            $orders->getCollection(),
            app()->getLocale(),
        );

        return view('account.orders.index', [
            'orders' => $orders,
            'rows' => $rows,
        ]);
    }

    public function show(Request $request, ParentOrder $parentOrder): View
    {
        $this->authorize('view', $parentOrder);

        $user = $request->user();
        $parentOrder->loadMissing('vendorOrders');

        $view = $this->orderViews->parent($parentOrder, app()->getLocale());
        $canCancel = $user->can('cancel', $parentOrder)
            && $this->cancellations->customerCanCancelParent($parentOrder);

        return view('account.orders.show', [
            'order' => $view,
            'canCancel' => $canCancel,
        ]);
    }

    public function cancel(
        CancelParentOrderRequest $request,
        ParentOrder $parentOrder,
    ): RedirectResponse {
        $user = $request->user();

        try {
            $this->cancellations->cancelParentByCustomer($user, $parentOrder);
        } catch (OrderCancellationException $e) {
            if ($e->errorCode === OrderCancellationException::UNAUTHORIZED) {
                abort(404);
            }

            return redirect()
                ->route('account.orders.show', $parentOrder)
                ->withErrors([
                    'cancel' => __('This order cannot be cancelled.'),
                ]);
        }

        return redirect()
            ->route('account.orders.show', $parentOrder)
            ->with('status', __('Order cancelled.'));
    }
}
