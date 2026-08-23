<?php

namespace App\Http\Requests\Vendor;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Http\FormRequest;

class ReorderProductImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            && ($this->user()?->can('update', $product) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image_ids' => ['required', 'array', 'min:1', 'max:'.ProductImage::MAX_PER_PRODUCT],
            'image_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
