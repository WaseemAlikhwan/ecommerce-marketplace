<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_orders', function (Blueprint $table) {
            $table->id();
            $table->string('public_code', 40)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 32);
            $table->string('shipping_recipient_name');
            $table->string('shipping_phone', 32);
            $table->foreignId('shipping_governorate_id')->nullable()->constrained('governorates')->nullOnDelete();
            $table->foreignId('shipping_city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('shipping_governorate_name_ar');
            $table->string('shipping_governorate_name_en');
            $table->string('shipping_city_name_ar');
            $table->string('shipping_city_name_en');
            $table->char('shipping_country_code', 2)->default('SY');
            $table->string('shipping_line1');
            $table->string('shipping_line2')->nullable();
            $table->text('shipping_notes')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('vendor_orders', function (Blueprint $table) {
            $table->id();
            $table->string('public_code', 40)->unique();
            $table->foreignId('parent_order_id')->constrained('parent_orders')->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('store_name');
            $table->char('currency_code', 3);
            $table->string('status', 32);
            $table->unsignedBigInteger('items_subtotal_amount_minor');
            $table->unsignedBigInteger('shipping_amount_minor');
            $table->unsignedBigInteger('grand_total_amount_minor');
            $table->unsignedInteger('commission_rate_bps');
            $table->unsignedBigInteger('commission_base_amount_minor');
            $table->unsignedBigInteger('commission_amount_minor');
            $table->timestamp('commission_recognized_at')->nullable();
            $table->string('shipping_recipient_name');
            $table->string('shipping_phone', 32);
            $table->foreignId('shipping_governorate_id')->nullable()->constrained('governorates')->nullOnDelete();
            $table->foreignId('shipping_city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('shipping_governorate_name_ar');
            $table->string('shipping_governorate_name_en');
            $table->string('shipping_city_name_ar');
            $table->string('shipping_city_name_en');
            $table->char('shipping_country_code', 2)->default('SY');
            $table->string('shipping_line1');
            $table->string('shipping_line2')->nullable();
            $table->text('shipping_notes')->nullable();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['vendor_id', 'status']);
            $table->index(['parent_order_id', 'vendor_id']);
            $table->unique(['parent_order_id', 'vendor_id']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_order_id')->constrained('vendor_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_amount_minor');
            $table->unsignedBigInteger('line_total_amount_minor');
            $table->char('currency_code', 3);
            $table->string('product_name_ar');
            $table->string('product_name_en');
            $table->string('sku');
            $table->string('store_name');
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index('vendor_order_id');
            $table->index('variant_id');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_order_id')->unique()->constrained('vendor_orders')->restrictOnDelete();
            $table->string('method', 32);
            $table->string('status', 32);
            $table->char('currency_code', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['method', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('vendor_orders');
        Schema::dropIfExists('parent_orders');
    }
};
