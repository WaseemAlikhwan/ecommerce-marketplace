<?php

namespace Database\Seeders;

use App\Models\CommissionSetting;
use Illuminate\Database\Seeder;

class CommissionSettingSeeder extends Seeder
{
    /**
     * Default platform commission rate in basis points (1000 = 10.00%).
     * Stored in DB so application code never hard-codes the live rate.
     */
    public function run(): void
    {
        if (CommissionSetting::query()->exists()) {
            return;
        }

        CommissionSetting::query()->create([
            'rate_bps' => (int) env('COMMISSION_DEFAULT_RATE_BPS', 1000),
        ]);
    }
}
