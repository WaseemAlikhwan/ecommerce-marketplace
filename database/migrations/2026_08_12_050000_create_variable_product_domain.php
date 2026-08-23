<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertDefaultVariantPreflight();

        Schema::table('attribute_values', function (Blueprint $table) {
            $table->unique(['id', 'attribute_id'], 'av_id_attribute_uq');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unique(['id', 'product_id'], 'pv_id_product_uq');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('default_variant_id')->nullable()->after('currency_code');
        });

        $this->backfillDefaultVariantId();

        Schema::table('products', function (Blueprint $table) {
            $table->foreign(['default_variant_id', 'id'], 'products_default_variant_fk')
                ->references(['id', 'product_id'])
                ->on('product_variants')
                ->restrictOnDelete();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });

        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('attribute_id');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id', 'pa_product_fk')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('attribute_id', 'pa_attr_fk')->references('id')->on('attributes')->restrictOnDelete();

            $table->unique(['product_id', 'attribute_id'], 'pa_product_attr_uq');
            $table->unique(['id', 'product_id'], 'pa_id_product_uq');
            $table->unique(['id', 'attribute_id'], 'pa_id_attr_uq');
            $table->index('attribute_id', 'pa_attr_idx');
        });

        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_attribute_id');
            $table->unsignedBigInteger('attribute_id');
            $table->unsignedBigInteger('attribute_value_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id', 'pav_product_fk')->references('id')->on('products')->restrictOnDelete();

            $table->foreign(['product_attribute_id', 'product_id'], 'pav_pa_product_fk')
                ->references(['id', 'product_id'])
                ->on('product_attributes')
                ->restrictOnDelete();

            $table->foreign(['product_attribute_id', 'attribute_id'], 'pav_pa_attr_fk')
                ->references(['id', 'attribute_id'])
                ->on('product_attributes')
                ->restrictOnDelete();

            $table->foreign(['attribute_value_id', 'attribute_id'], 'pav_av_attr_fk')
                ->references(['id', 'attribute_id'])
                ->on('attribute_values')
                ->restrictOnDelete();

            $table->unique(['product_attribute_id', 'attribute_value_id'], 'pav_pa_value_uq');
            $table->unique(['id', 'product_attribute_id'], 'pav_id_pa_uq');
            $table->index(['product_id', 'attribute_id'], 'pav_product_attr_idx');
        });

        Schema::create('product_variant_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('variant_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_attribute_id');
            $table->unsignedBigInteger('product_attribute_value_id');
            $table->timestamps();

            $table->foreign(['variant_id', 'product_id'], 'pva_variant_product_fk')
                ->references(['id', 'product_id'])
                ->on('product_variants')
                ->restrictOnDelete();

            $table->foreign(['product_attribute_id', 'product_id'], 'pva_pa_product_fk')
                ->references(['id', 'product_id'])
                ->on('product_attributes')
                ->restrictOnDelete();

            $table->foreign(['product_attribute_value_id', 'product_attribute_id'], 'pva_pav_pa_fk')
                ->references(['id', 'product_attribute_id'])
                ->on('product_attribute_values')
                ->restrictOnDelete();

            $table->unique(['variant_id', 'product_attribute_id'], 'pva_variant_pa_uq');
            $table->index('product_id', 'pva_product_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_attribute_values');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_attributes');

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign('products_default_variant_fk');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->boolean('is_default')->default(false);
        });

        DB::table('product_variants')->update(['is_default' => false]);

        $defaults = DB::table('products')
            ->whereNotNull('default_variant_id')
            ->get(['id', 'default_variant_id']);

        foreach ($defaults as $product) {
            DB::table('product_variants')
                ->where('id', $product->default_variant_id)
                ->where('product_id', $product->id)
                ->update(['is_default' => true]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('default_variant_id');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique('pv_id_product_uq');
        });

        Schema::table('attribute_values', function (Blueprint $table) {
            $table->dropUnique('av_id_attribute_uq');
        });
    }

    private function assertDefaultVariantPreflight(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('product_variants', 'is_default')) {
            return;
        }

        $anomalies = [];

        $productIds = DB::table('products')->pluck('id');

        foreach ($productIds as $productId) {
            $variants = DB::table('product_variants')->where('product_id', $productId)->get();

            if ($variants->isEmpty()) {
                $anomalies[] = "Product {$productId}: no variants";

                continue;
            }

            $defaults = $variants->filter(fn ($variant): bool => (int) $variant->is_default === 1);

            if ($defaults->count() !== 1) {
                $anomalies[] = "Product {$productId}: expected exactly one is_default=true, found {$defaults->count()}";

                continue;
            }

            $product = DB::table('products')->where('id', $productId)->first();
            $default = $defaults->first();

            if ($product?->deleted_at === null && $default->deleted_at !== null) {
                $anomalies[] = "Product {$productId}: live product default variant {$default->id} is soft-deleted";
            }
        }

        if ($anomalies !== []) {
            throw new RuntimeException(
                "S4B1 default-variant preflight failed:\n".implode("\n", $anomalies)
            );
        }
    }

    private function backfillDefaultVariantId(): void
    {
        $rows = DB::table('product_variants')
            ->where('is_default', true)
            ->get(['id', 'product_id']);

        foreach ($rows as $variant) {
            DB::table('products')
                ->where('id', $variant->product_id)
                ->update(['default_variant_id' => $variant->id]);
        }
    }
};
