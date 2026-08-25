<?php

namespace App\Models;

use App\Enums\CouponRedemptionStatus;
use Database\Factories\CouponRedemptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Coupon redemption row (CPN-A). Counts toward usage limits while status is active.
 *
 * No public SKU or exact inventory quantity is stored on this model.
 */
#[Fillable([
    'coupon_id',
    'user_id',
    'parent_order_id',
    'vendor_order_id',
    'discount_amount_minor',
    'currency_code',
    'status',
])]
class CouponRedemption extends Model
{
    /** @use HasFactory<CouponRedemptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_amount_minor' => 'integer',
            'status' => CouponRedemptionStatus::class,
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parentOrder(): BelongsTo
    {
        return $this->belongsTo(ParentOrder::class);
    }

    public function vendorOrder(): BelongsTo
    {
        return $this->belongsTo(VendorOrder::class);
    }

    public function isActive(): bool
    {
        return $this->status === CouponRedemptionStatus::Active;
    }
}
