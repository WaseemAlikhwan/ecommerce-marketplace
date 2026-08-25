<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64);
            $table->string('scope', 20);
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->restrictOnDelete();
            $table->string('type', 20);
            $table->unsignedBigInteger('value');
            $table->string('currency_code', 3);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('min_eligible_amount_minor')->default(0);
            $table->unsignedBigInteger('max_discount_amount_minor')->nullable();
            $table->unsignedInteger('global_usage_limit')->nullable();
            $table->unsignedInteger('per_user_usage_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('code');
            $table->index(['scope', 'vendor_id', 'is_active']);
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::create('coupon_product', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->primary(['coupon_id', 'product_id']);
        });

        Schema::create('coupon_category', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->primary(['coupon_id', 'category_id']);
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('parent_order_id')->nullable()->constrained('parent_orders')->nullOnDelete();
            $table->foreignId('vendor_order_id')->nullable()->constrained('vendor_orders')->nullOnDelete();
            $table->unsignedBigInteger('discount_amount_minor');
            $table->string('currency_code', 3);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['coupon_id', 'user_id', 'status']);
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupon_category');
        Schema::dropIfExists('coupon_product');
        Schema::dropIfExists('coupons');
    }
};
