<?php

namespace App\Http\Controllers\Admin;

use App\Admin\AdminDashboardStats;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Services\AdminDashboardStatsService;
use App\Support\Money;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardStatsService $stats,
    ) {}

    public function __invoke(): View
    {
        $this->authorize('viewAny', AdminDashboardStats::class);

        $snapshot = $this->stats->snapshot();
        $exponents = Currency::query()->pluck('exponent', 'code');

        $recognizedCommissionLabels = [];
        foreach ($snapshot->recognizedCommissionAmountMinorByCurrency as $code => $minor) {
            $exponent = (int) ($exponents[$code] ?? 0);
            $recognizedCommissionLabels[$code] = Money::formatFromMinor((int) $minor, $exponent).' '.$code;
        }

        return view('admin.dashboard', [
            'stats' => $snapshot,
            'recognizedCommissionLabels' => $recognizedCommissionLabels,
        ]);
    }
}
