<?php

namespace App\Models;

use App\Enums\ProductReviewStatus;
use Database\Factories\ProductReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Customer product review (REV-A / OPEN-008+009 V1).
 *
 * No public SKU or exact inventory quantity is stored on this model.
 */
#[Fillable([
    'user_id',
    'product_id',
    'rating',
    'body',
    'status',
])]
class ProductReview extends Model
{
    /** @use HasFactory<ProductReviewFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => ProductReviewStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isApproved(): bool
    {
        return $this->status === ProductReviewStatus::Approved;
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id;
    }
}
