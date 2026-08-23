<?php

namespace App\Http\Requests\Admin;

use App\Models\Brand;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Brand::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash:ascii', Rule::unique('brands', 'slug')],
            'is_active' => ['boolean'],
            'translations' => ['required', 'array'],
            'translations.ar.name' => ['required', 'string', 'max:120'],
            'translations.ar.description' => ['nullable', 'string', 'max:2000'],
            'translations.en.name' => ['required', 'string', 'max:120'],
            'translations.en.description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'translations.ar.name' => __('Arabic name'),
            'translations.en.name' => __('English name'),
            'translations.ar.description' => __('Arabic description'),
            'translations.en.description' => __('English description'),
            'slug' => __('Slug'),
        ];
    }
}
