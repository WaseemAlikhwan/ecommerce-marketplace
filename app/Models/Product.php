<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\StoreStatus;
use App\Enums\VendorStatus;
use App\Support\Locale;
use App\Support\Money;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'store_id',
    'category_id',
    'brand_id',
    'slug',
    'type',
    'status',
    'currency_code',
    'published_at',
    'suspended_at',
    'suspended_by',
    'suspension_reason',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'published_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function defaultVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'default_variant_id')->withTrashed();
    }

    public function productAttributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    public function productAttributesWithTrashed(): HasMany
    {
        return $this->hasMany(ProductAttribute::class)->withTrashed()->orderBy('position')->orderBy('id');
    }

    public function variantsWithTrashed(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->withTrashed()->orderBy('id');
    }

    public function productAttributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function variantAttributeValues(): HasMany
    {
        return $this->hasMany(ProductVariantAttributeValue::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    public function primaryImage(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'primary_image_id');
    }

    public function translation(?string $locale = null): ?ProductTranslation
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

    public function name(?string $locale = null): string
    {
        return $this->translation($locale)?->name ?? $this->slug;
    }

    public function hasMissingTranslation(?string $locale = null): bool
    {
        $locale = Locale::sanitize($locale ?? app()->getLocale());

        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return ! $translations->contains(fn (ProductTranslation $row): bool => $row->locale === $locale && filled($row->name));
    }

    public function allowsStructuralMatrixSync(): bool
    {
        return $this->status === ProductStatus::Draft && $this->published_at === null;
    }

    public function formattedLivePriceRange(): string
    {
        $variants = $this->relationLoaded('variants')
            ? $this->variants
            : $this->variants()->get();

        if ($variants->isEmpty()) {
            return '—';
        }

        $min = (int) $variants->min('price_amount_minor');
        $max = (int) $variants->max('price_amount_minor');
        $exponent = (int) ($this->currency?->exponent ?? $this->currency()->value('exponent') ?? 0);
        $minLabel = Money::formatFromMinor($min, $exponent);

        if ($min === $max) {
            return $minLabel;
        }

        return $minLabel.' – '.Money::formatFromMinor($max, $exponent);
    }

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where($this->qualifyColumn('store_id'), $storeId);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('status'), ProductStatus::Published->value);
    }

    /**
     * SQL storefront-visibility gate aligned with readiness visibility signals.
     * Does not evaluate ProductReadinessService per row.
     * Category ancestry uses fixed-depth joins (Category::MAX_DEPTH === 3).
     */
    public function scopeStorefrontVisible(Builder $query): Builder
    {
        $product = $query->getModel();
        $table = $product->getTable();

        return $query
            ->whereNull($product->qualifyColumn('deleted_at'))
            ->where($product->qualifyColumn('status'), ProductStatus::Published->value)
            ->whereNotNull($product->qualifyColumn('category_id'))
            ->whereExists(function ($sub) use ($table): void {
                $sub->selectRaw('1')
                    ->from('stores')
                    ->join('vendors', 'vendors.id', '=', 'stores.vendor_id')
                    ->whereColumn('stores.id', "{$table}.store_id")
                    ->where('stores.status', StoreStatus::Active->value)
                    ->where('vendors.status', VendorStatus::Approved->value);
            })
            ->whereExists(function ($sub) use ($table): void {
                $sub->selectRaw('1')
                    ->from('categories as category_leaf')
                    ->leftJoin('categories as category_parent', 'category_parent.id', '=', 'category_leaf.parent_id')
                    ->leftJoin('categories as category_grandparent', 'category_grandparent.id', '=', 'category_parent.parent_id')
                    ->whereColumn('category_leaf.id', "{$table}.category_id")
                    ->where('category_leaf.is_active', true)
                    ->whereNotExists(function ($children): void {
                        $children->selectRaw('1')
                            ->from('categories as category_children')
                            ->whereColumn('category_children.parent_id', 'category_leaf.id');
                    })
                    ->where(function ($ancestors): void {
                        $ancestors
                            ->whereNull('category_leaf.parent_id')
                            ->orWhere('category_parent.is_active', true);
                    })
                    ->where(function ($ancestors): void {
                        $ancestors
                            ->whereNull('category_leaf.parent_id')
                            ->orWhereNull('category_parent.parent_id')
                            ->orWhere('category_grandparent.is_active', true);
                    })
                    // Reject malformed depth greater than Category::MAX_DEPTH (3).
                    ->where(function ($depth): void {
                        $depth
                            ->whereNull('category_leaf.parent_id')
                            ->orWhereNull('category_parent.parent_id')
                            ->orWhereNull('category_grandparent.parent_id');
                    });
            })
            ->where(function (Builder $brandQuery) use ($table): void {
                $brandQuery
                    ->whereNull("{$table}.brand_id")
                    ->orWhereExists(function ($sub) use ($table): void {
                        $sub->selectRaw('1')
                            ->from('brands')
                            ->whereColumn('brands.id', "{$table}.brand_id")
                            ->where('brands.is_active', true);
                    });
            })
            ->whereExists(function ($sub) use ($table): void {
                $sub->selectRaw('1')
                    ->from('currencies')
                    ->whereColumn('currencies.code', "{$table}.currency_code")
                    ->where('currencies.is_active', true);
            });
    }
}
