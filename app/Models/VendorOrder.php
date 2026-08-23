<?php

namespace App\Models;

use App\Enums\VendorOrderStatus;
use Database\Factories\VendorOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'public_code',
    'parent_order_id',
    'vendor_id',
    'store_id',
    'store_name',
    'currency_code',
    'status',
    'items_subtotal_amount_minor',
    'shipping_amount_minor',
    'grand_total_amount_minor',
    'commission_rate_bps',
    'commission_base_amount_minor',
    'commission_amount_minor',
    'commission_recognized_at',
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
])]
class VendorOrder extends Model
{
    /** @use HasFactory<VendorOrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VendorOrderStatus::class,
            'items_subtotal_amount_minor' => 'integer',
            'shipping_amount_minor' => 'integer',
            'grand_total_amount_minor' => 'integer',
            'commission_rate_bps' => 'integer',
            'commission_base_amount_minor' => 'integer',
            'commission_amount_minor' => 'integer',
            'commission_recognized_at' => 'datetime',
        ];
    }

    public function parentOrder(): BelongsTo
    {
        return $this->belongsTo(ParentOrder::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
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
