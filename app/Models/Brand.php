<?php

namespace App\Models;

use App\Support\Locale;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'is_active'])]
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(BrandTranslation::class);
    }

    public function translation(?string $locale = null): ?BrandTranslation
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
        return $this->translation($locale)?->name ?? $this->slug;
    }

    public function description(?string $locale = null): ?string
    {
        return $this->translation($locale)?->description;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
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
