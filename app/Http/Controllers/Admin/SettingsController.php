<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionSetting;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __invoke(): View
    {
        $this->authorize('viewAny', CommissionSetting::class);

        $setting = CommissionSetting::query()->orderByDesc('id')->first();
        $rateBps = $setting !== null ? (int) $setting->rate_bps : null;
        $ratePercentLabel = $rateBps === null
            ? '—'
            : rtrim(rtrim(number_format($rateBps / 100, 2, '.', ''), '0'), '.').'%';

        return view('admin.settings.show', [
            'rateBps' => $rateBps,
            'ratePercentLabel' => $ratePercentLabel,
            'updatedAtLabel' => $setting?->updated_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—',
        ]);
    }
}
