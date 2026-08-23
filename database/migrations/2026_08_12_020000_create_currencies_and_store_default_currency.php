<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->char('code', 3)->primary();
            $table->unsignedTinyInteger('exponent');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

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

        Schema::table('stores', function (Blueprint $table) {
            $table->char('default_currency_code', 3)->default('SYP')->after('rating');
        });

        DB::table('stores')
            ->whereNull('default_currency_code')
            ->orWhere('default_currency_code', '')
            ->update(['default_currency_code' => 'SYP']);

        Schema::table('stores', function (Blueprint $table) {
            $table->foreign('default_currency_code')
                ->references('code')
                ->on('currencies')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['default_currency_code']);
            $table->dropColumn('default_currency_code');
        });

        Schema::dropIfExists('currencies');
    }
};
