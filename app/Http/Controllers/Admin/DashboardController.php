<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VendorStatus;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorApplication;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'pendingApplications' => VendorApplication::query()->pending()->count(),
            'approvedVendors' => Vendor::query()->where('status', VendorStatus::Approved)->count(),
        ]);
    }
}
