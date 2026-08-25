<?php

namespace App\Http\Requests\Account;

use App\Models\ProductReview;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProductReview|null $productReview */
        $productReview = $this->route('productReview');

        return $productReview instanceof ProductReview
            && ($this->user()?->can('update', $productReview) ?? false);
    }

    protected function failedAuthorization(): void
    {
        abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function rating(): int
    {
        return (int) $this->validated('rating');
    }

    public function body(): ?string
    {
        $body = $this->validated('body');

        return is_string($body) ? $body : null;
    }
}
