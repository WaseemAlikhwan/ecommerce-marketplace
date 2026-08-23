<?php

namespace App\Models;

use App\Support\Locale;
use Database\Factories\AttributeValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['attribute_id', 'code', 'position', 'is_active'])]
class AttributeValue extends Model
{
    /** @use HasFactory<AttributeValueFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(AttributeValueTranslation::class);
    }

    public function translation(?string $locale = null): ?AttributeValueTranslation
    {
        $locale = Locale::sanitize($locale ?? app()->getLocale());

        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        return $translations->firstWhere('locale', $locale)
            ?? $translations->firstWhere('locale', 'en')
            ?? $translations->first();
    }

    public function name(?string $locale = null): string
    {
        return $this->translation($locale)?->name ?? $this->code;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    public function scopeSearchByName(Builder $query, ?string $term, ?string $locale = null): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $locale = Locale::sanitize($locale ?? app()->getLocale());

        return $query->whereHas('translations', function (Builder $translations) use ($term, $locale): void {
            $translations
                ->where('locale', $locale)
                ->where('name', 'like', '%'.$term.'%');
        });
    }
}
