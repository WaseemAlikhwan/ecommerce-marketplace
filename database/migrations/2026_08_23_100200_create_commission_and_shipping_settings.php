<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('rate_bps');
            $table->timestamps();
        });

        Schema::create('vendor_commission_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->unique()->constrained('vendors')->cascadeOnDelete();
            $table->unsignedInteger('rate_bps');
            $table->timestamps();
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedBigInteger('flat_shipping_amount_minor')->nullable()->after('default_currency_code');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('flat_shipping_amount_minor');
        });

        Schema::dropIfExists('vendor_commission_overrides');
        Schema::dropIfExists('commission_settings');
    }
};
