<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'product_id',
    'store_id',
    'sku',
    'combination_key',
    'price_amount_minor',
    'compare_at_amount_minor',
    'quantity',
])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory, SoftDeletes;

    public const DEFAULT_COMBINATION_KEY = 'default';

    /** MySQL UNSIGNED INTEGER maximum for quantity. */
    public const MAX_QUANTITY = 4_294_967_295;

    public const MAX_LIVE_PER_PRODUCT = 48;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_amount_minor' => 'integer',
            'compare_at_amount_minor' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function attributeValueLinks(): HasMany
    {
        return $this->hasMany(ProductVariantAttributeValue::class, 'variant_id');
    }
}
