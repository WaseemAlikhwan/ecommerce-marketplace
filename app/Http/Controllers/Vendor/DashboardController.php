<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $vendor = auth()->user()->vendor()->with('store')->firstOrFail();

        return view('vendor.dashboard', [
            'vendor' => $vendor,
            'store' => $vendor->store,
        ]);
    }
}
