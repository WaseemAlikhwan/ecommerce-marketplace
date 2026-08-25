<?php

namespace App\Models;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Coupon definition (CPN-A / OPEN-007 V1).
 *
 * No public SKU or exact inventory quantity is stored on this model.
 */
#[Fillable([
    'code',
    'scope',
    'vendor_id',
    'type',
    'value',
    'currency_code',
    'starts_at',
    'ends_at',
    'min_eligible_amount_minor',
    'max_discount_amount_minor',
    'global_usage_limit',
    'per_user_usage_limit',
    'is_active',
])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => CouponScope::class,
            'type' => CouponType::class,
            'value' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'min_eligible_amount_minor' => 'integer',
            'max_discount_amount_minor' => 'integer',
            'global_usage_limit' => 'integer',
            'per_user_usage_limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_product');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'coupon_category');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function isPlatform(): bool
    {
        return $this->scope === CouponScope::Platform;
    }

    public function isVendorScoped(): bool
    {
        return $this->scope === CouponScope::Vendor;
    }
}
