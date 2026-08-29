<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\VendorOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\VendorOrder;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $vendor = auth()->user()->vendor()->with('store')->firstOrFail();

        $pendingOrderCount = VendorOrder::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', VendorOrderStatus::Pending)
            ->count();

        $deliveredOrderCount = VendorOrder::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', VendorOrderStatus::Delivered)
            ->count();

        return view('vendor.dashboard', [
            'vendor' => $vendor,
            'store' => $vendor->store,
            'pendingOrderCount' => $pendingOrderCount,
            'deliveredOrderCount' => $deliveredOrderCount,
        ]);
    }
}
