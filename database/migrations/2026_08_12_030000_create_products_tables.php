<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->restrictOnDelete();
            $table->string('slug')->unique();
            $table->string('type', 20);
            $table->string('status', 20)->default('draft');
            $table->char('currency_code', 3);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('suspension_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();

            $table->unique(['id', 'store_id']);
            $table->index(['store_id', 'status']);
            $table->index('category_id');
            $table->index('brand_id');
            $table->index('currency_code');
        });

        Schema::create('product_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name');
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'locale']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('store_id');
            $table->string('sku');
            $table->boolean('is_default')->default(false);
            $table->string('combination_key');
            $table->unsignedBigInteger('price_amount_minor');
            $table->unsignedBigInteger('compare_at_amount_minor')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign(['product_id', 'store_id'])
                ->references(['id', 'store_id'])
                ->on('products')
                ->restrictOnDelete();

            $table->unique(['store_id', 'sku']);
            $table->unique(['product_id', 'combination_key']);
            $table->index('product_id');
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_translations');
        Schema::dropIfExists('products');
    }
};
