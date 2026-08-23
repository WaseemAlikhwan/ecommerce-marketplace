<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('store_id');
            $table->string('path');
            $table->string('mime_type', 32);
            $table->unsignedInteger('size_bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique('path', 'pi_path_uq');
            $table->unique(['id', 'product_id'], 'pi_id_product_uq');
            $table->unique(['product_id', 'position'], 'pi_product_pos_uq');
            $table->index('store_id', 'pi_store_idx');

            $table->foreign(['product_id', 'store_id'], 'pi_product_store_fk')
                ->references(['id', 'store_id'])
                ->on('products')
                ->restrictOnDelete();
        });

        Schema::create('product_image_translations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_image_id');
            $table->string('locale', 5);
            $table->string('alt_text');
            $table->timestamps();

            $table->unique(['product_image_id', 'locale'], 'pit_image_locale_uq');
            $table->foreign('product_image_id', 'pit_image_fk')
                ->references('id')
                ->on('product_images')
                ->cascadeOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('primary_image_id')->nullable()->after('default_variant_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign(['primary_image_id', 'id'], 'products_primary_image_fk')
                ->references(['id', 'product_id'])
                ->on('product_images')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (Schema::hasColumn('products', 'primary_image_id')) {
            Schema::table('products', function (Blueprint $table) use ($driver) {
                if ($driver === 'sqlite') {
                    $table->dropForeign(['primary_image_id', 'id']);
                } else {
                    $table->dropForeign('products_primary_image_fk');
                }
                $table->dropColumn('primary_image_id');
            });
        }

        Schema::dropIfExists('product_image_translations');
        Schema::dropIfExists('product_images');
    }
};
