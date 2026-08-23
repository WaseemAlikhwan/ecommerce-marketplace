<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    /**
     * Idempotent reference currencies for Catalog Slice S2.
     * Migration already inserts these rows; seeder keeps local/demo DBs aligned.
     */
    public function run(): void
    {
        $now = now();

        DB::table('currencies')->upsert([
            [
                'code' => 'SYP',
                'exponent' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'USD',
                'exponent' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['code'], ['exponent', 'is_active', 'updated_at']);
    }
}
