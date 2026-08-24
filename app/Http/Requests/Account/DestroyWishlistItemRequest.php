<?php

namespace App\Http\Requests\Account;

use App\Models\WishlistItem;
use Illuminate\Foundation\Http\FormRequest;

class DestroyWishlistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WishlistItem|null $wishlistItem */
        $wishlistItem = $this->route('wishlistItem');

        return $wishlistItem instanceof WishlistItem
            && ($this->user()?->can('delete', $wishlistItem) ?? false);
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
