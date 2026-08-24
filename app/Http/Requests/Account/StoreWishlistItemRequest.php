<?php

namespace App\Http\Requests\Account;

use App\Models\Product;
use App\Models\WishlistItem;
use Illuminate\Foundation\Http\FormRequest;

class StoreWishlistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Product|null $product */
        $product = $this->route('product');

        return $product instanceof Product
            && ($this->user()?->can('create', WishlistItem::class) ?? false);
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
        return [];
    }
}
