<?php

namespace App\Http\Requests\Vendor;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductImageTranslationsRequest extends FormRequest
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
            'translations' => ['required', 'array'],
            'translations.ar' => ['sometimes', 'array'],
            'translations.en' => ['sometimes', 'array'],
            'translations.ar.alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'translations.en.alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
