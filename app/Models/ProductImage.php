<?php

namespace App\Models;

use App\Support\Locale;
use Database\Factories\ProductImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'product_id',
    'store_id',
    'path',
    'mime_type',
    'size_bytes',
    'width',
    'height',
    'position',
])]
class ProductImage extends Model
{
    /** @use HasFactory<ProductImageFactory> */
    use HasFactory;

    public const MAX_PER_PRODUCT = 8;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'position' => 'integer',
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

    public function translations(): HasMany
    {
        return $this->hasMany(ProductImageTranslation::class);
    }

    public function translation(?string $locale = null): ?ProductImageTranslation
    {
        $locale = Locale::sanitize($locale ?? app()->getLocale());

        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $translations->firstWhere('locale', $locale)
            ?? $translations->firstWhere('locale', 'en')
            ?? $translations->firstWhere('locale', 'ar')
            ?? $translations->first();
    }

    public function altText(?string $locale = null): string
    {
        $locale = Locale::sanitize($locale ?? app()->getLocale());
        $translated = $this->translation($locale)?->alt_text;

        if (filled($translated)) {
            return (string) $translated;
        }

        $product = $this->relationLoaded('product')
            ? $this->product
            : $this->product()->with('translations')->first();

        return $product?->name($locale) ?? '';
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function belongsToProduct(Product $product): bool
    {
        return (int) $this->product_id === (int) $product->id
            && (int) $this->store_id === (int) $product->store_id;
    }
}
