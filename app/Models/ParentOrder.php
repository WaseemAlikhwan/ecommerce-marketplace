<?php

namespace App\Models;

use App\Enums\ParentOrderStatus;
use Database\Factories\ParentOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'public_code',
    'user_id',
    'status',
    'coupon_id',
    'coupon_code',
    'shipping_recipient_name',
    'shipping_phone',
    'shipping_governorate_id',
    'shipping_city_id',
    'shipping_governorate_name_ar',
    'shipping_governorate_name_en',
    'shipping_city_name_ar',
    'shipping_city_name_en',
    'shipping_country_code',
    'shipping_line1',
    'shipping_line2',
    'shipping_notes',
    'placed_at',
])]
class ParentOrder extends Model
{
    /** @use HasFactory<ParentOrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ParentOrderStatus::class,
            'placed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendorOrders(): HasMany
    {
        return $this->hasMany(VendorOrder::class);
    }

    public function shippingGovernorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class, 'shipping_governorate_id');
    }

    public function shippingCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'shipping_city_id');
    }
}
