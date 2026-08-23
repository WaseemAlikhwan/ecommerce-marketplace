<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'exponent', 'is_active'])]
class Currency extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exponent' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class, 'default_currency_code', 'code');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'currency_code', 'code');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function label(): string
    {
        return match ($this->code) {
            'SYP' => __('Syrian Pound (SYP)'),
            'USD' => __('US Dollar (USD)'),
            default => $this->code,
        };
    }
}
