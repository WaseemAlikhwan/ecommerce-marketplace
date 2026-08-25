<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductReview;
use Illuminate\Foundation\Http\FormRequest;

class ApproveProductReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $review = $this->route('productReview');

        return $review instanceof ProductReview
            && ($this->user()?->can('moderate', $review) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
