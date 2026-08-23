<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\ParentOrder;
use App\Services\OrderViewService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ParentOrderController extends Controller
{
    public function __construct(
        private readonly OrderViewService $orderViews,
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

    public function show(ParentOrder $parentOrder): View
    {
        $this->authorize('view', $parentOrder);

        $view = $this->orderViews->parent($parentOrder, app()->getLocale());

        return view('account.orders.show', [
            'order' => $view,
        ]);
    }
}
