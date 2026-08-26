<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParentOrder;
use App\Services\OrderViewService;
use Illuminate\View\View;

class ParentOrderController extends Controller
{
    public function __construct(
        private readonly OrderViewService $orderViews,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', ParentOrder::class);

        $orders = ParentOrder::query()
            ->with(['user', 'vendorOrders.payment'])
            ->latest('placed_at')
            ->latest('id')
            ->paginate(20);

        $rows = $this->orderViews->adminParentIndexRows(
            $orders->getCollection(),
            app()->getLocale(),
        );

        return view('admin.orders.index', [
            'orders' => $orders,
            'rows' => $rows,
        ]);
    }

    public function show(ParentOrder $parentOrder): View
    {
        $this->authorize('view', $parentOrder);

        $detail = $this->orderViews->adminParentDetail($parentOrder, app()->getLocale());

        return view('admin.orders.show', [
            'order' => $detail,
        ]);
    }
}
