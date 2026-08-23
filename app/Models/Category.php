<?php

namespace App\Models;

use App\Support\Locale;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['parent_id', 'slug', 'position', 'is_active'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    public const MAX_DEPTH = 3;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    public function translation(?string $locale = null): ?CategoryTranslation
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

    public function depth(): int
    {
        $depth = 1;
        $parent = $this->parent;

        while ($parent !== null) {
            $depth++;
            $parent = $parent->parent;
        }

        return $depth;
    }

    public function isLeaf(): bool
    {
        if ($this->relationLoaded('children')) {
            return $this->children->isEmpty();
        }

        return ! $this->children()->exists();
    }

    public function ancestorsAreActive(): bool
    {
        $parent = $this->relationLoaded('parent') ? $this->parent : $this->parent()->first();

        while ($parent !== null) {
            if (! $parent->is_active) {
                return false;
            }

            $parent = $parent->relationLoaded('parent')
                ? $parent->parent
                : $parent->parent()->first();
        }

        return true;
    }

    public function isAssignableLeaf(): bool
    {
        return $this->is_active && $this->isLeaf() && $this->ancestorsAreActive();
    }

    public function descendantIds(): Collection
    {
        $ids = collect();

        $walk = function (self $category) use (&$walk, &$ids): void {
            foreach ($category->children as $child) {
                $ids->push($child->id);
                $walk($child);
            }
        };

        $this->loadMissing('children');
        $walk($this);

        return $ids;
    }

    public function subtreeHeightBelow(): int
    {
        $this->loadMissing('children');

        if ($this->children->isEmpty()) {
            return 0;
        }

        return 1 + $this->children->max(fn (self $child): int => $child->subtreeHeightBelow());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Categories eligible for public storefront navigation (SQL-only).
     * Self must be active, ancestors active when present, depth ≤ MAX_DEPTH.
     * Non-leaf parents remain navigable when they satisfy the same gate.
     */
    public function scopeStorefrontNavigable(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->where("{$table}.is_active", true)
            ->where(function (Builder $depth) use ($table): void {
                $depth
                    // Depth 1 (root)
                    ->whereNull("{$table}.parent_id")
                    // Depth 2: active parent that is a root
                    ->orWhereExists(function ($sub) use ($table): void {
                        $sub->selectRaw('1')
                            ->from('categories as category_parent')
                            ->whereColumn('category_parent.id', "{$table}.parent_id")
                            ->where('category_parent.is_active', true)
                            ->whereNull('category_parent.parent_id');
                    })
                    // Depth 3: active parent + active root grandparent (no further ancestor)
                    ->orWhereExists(function ($sub) use ($table): void {
                        $sub->selectRaw('1')
                            ->from('categories as category_parent')
                            ->join('categories as category_grandparent', 'category_grandparent.id', '=', 'category_parent.parent_id')
                            ->whereColumn('category_parent.id', "{$table}.parent_id")
                            ->where('category_parent.is_active', true)
                            ->where('category_grandparent.is_active', true)
                            ->whereNull('category_grandparent.parent_id');
                    });
            });
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
