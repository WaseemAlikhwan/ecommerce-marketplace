<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VendorOrder;
use App\Services\OrderViewService;
use Illuminate\View\View;

class VendorOrderController extends Controller
{
    public function __construct(
        private readonly OrderViewService $orderViews,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', VendorOrder::class);

        $orders = VendorOrder::query()
            ->with(['payment', 'parentOrder', 'currency'])
            ->latest('id')
            ->paginate(20);

        $rows = $this->orderViews->adminVendorIndexRows(
            $orders->getCollection(),
            app()->getLocale(),
        );

        return view('admin.vendor-orders.index', [
            'orders' => $orders,
            'rows' => $rows,
        ]);
    }

    public function show(VendorOrder $vendorOrder): View
    {
        $this->authorize('view', $vendorOrder);

        $detail = $this->orderViews->adminVendorDetail($vendorOrder, app()->getLocale());

        return view('admin.vendor-orders.show', [
            'order' => $detail,
        ]);
    }
}
