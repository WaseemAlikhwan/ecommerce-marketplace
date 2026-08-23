<?php

namespace App\Models;

use App\Enums\StoreStatus;
use App\Enums\VendorStatus;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'vendor_id',
    'name',
    'slug',
    'description',
    'logo_path',
    'banner_path',
    'contact_email',
    'contact_phone',
    'status',
    'rating',
    'default_currency_code',
    'flat_shipping_amount_minor',
])]
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StoreStatus::class,
            'rating' => 'decimal:2',
            'flat_shipping_amount_minor' => 'integer',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function defaultCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'default_currency_code', 'code');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function isSellable(): bool
    {
        return $this->status->isSellable();
    }

    /**
     * Stores eligible for public storefront pages (SQL-only):
     * Store Active + Vendor Approved.
     */
    public function scopePubliclyEligible(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->where("{$table}.status", StoreStatus::Active->value)
            ->whereExists(function ($sub) use ($table): void {
                $sub->selectRaw('1')
                    ->from('vendors')
                    ->whereColumn('vendors.id', "{$table}.vendor_id")
                    ->where('vendors.status', VendorStatus::Approved->value);
            });
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function bannerUrl(): ?string
    {
        return $this->banner_path ? Storage::disk('public')->url($this->banner_path) : null;
    }
}
