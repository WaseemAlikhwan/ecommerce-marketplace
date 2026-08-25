<?php

namespace App\Http\Requests\Account;

use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Product|null $product */
        $product = $this->route('product');

        return $product instanceof Product
            && ($this->user()?->can('create', ProductReview::class) ?? false);
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
