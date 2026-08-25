<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['user_id', 'product_id']);
            $table->index(['product_id', 'status']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('approved_reviews_count')->default(0)->after('published_at');
            $table->decimal('approved_rating_average', 3, 2)->nullable()->after('approved_reviews_count');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['approved_reviews_count', 'approved_rating_average']);
        });

        Schema::dropIfExists('product_reviews');
    }
};
