<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_amount_minor')->default(0)->after('shipping_amount_minor');
            $table->string('coupon_code', 64)->nullable()->after('discount_amount_minor');
            $table->foreignId('coupon_id')->nullable()->after('coupon_code')->constrained('coupons')->nullOnDelete();
        });

        Schema::table('parent_orders', function (Blueprint $table) {
            $table->string('coupon_code', 64)->nullable()->after('status');
            $table->foreignId('coupon_id')->nullable()->after('coupon_code')->constrained('coupons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('parent_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('coupon_code');
        });

        Schema::table('vendor_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['discount_amount_minor', 'coupon_code']);
        });
    }
};
