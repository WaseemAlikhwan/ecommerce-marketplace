<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorOrder;
use App\Services\OrderViewService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class VendorOrderController extends Controller
{
    public function __construct(
        private readonly OrderViewService $orderViews,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', VendorOrder::class);

        $user = $request->user();
        $user->loadMissing(['roles', 'vendor']);
        $vendor = $user->vendor;
        abort_unless($vendor !== null && $user->canAccessVendorPanel(), 404);

        $orders = VendorOrder::query()
            ->where('vendor_id', $vendor->id)
            ->with(['payment', 'parentOrder', 'currency'])
            ->latest('id')
            ->paginate(20);

        $rows = $this->orderViews->vendorIndexRows(
            $orders->getCollection(),
            app()->getLocale(),
        );

        return view('vendor.orders.index', [
            'orders' => $orders,
            'rows' => $rows,
        ]);
    }

    public function show(Request $request, VendorOrder $vendorOrder): View
    {
        $user = $request->user();
        $user->loadMissing(['roles', 'vendor']);
        abort_unless($user->canAccessVendorPanel(), 404);

        // Own-vendor only: fail closed as 404 (do not leak existence to other vendors).
        if ($user->vendor?->id !== $vendorOrder->vendor_id) {
            abort(404);
        }

        $this->authorize('view', $vendorOrder);

        $view = $this->orderViews->vendor($vendorOrder, app()->getLocale());

        return view('vendor.orders.show', [
            'order' => $view,
        ]);
    }
}
