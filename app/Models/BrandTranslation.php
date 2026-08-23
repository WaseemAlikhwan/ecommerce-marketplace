<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['brand_id', 'locale', 'name', 'description'])]
class BrandTranslation extends Model
{
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
