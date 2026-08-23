<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category
            && ($this->user()?->can('update', $category) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'parent_id' => $this->filled('parent_id') ? $this->input('parent_id') : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Category $category */
        $category = $this->route('category');

        return [
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'alpha_dash:ascii',
                Rule::unique('categories', 'slug')->ignore($category->id),
            ],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
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
            'parent_id' => __('Parent category'),
            'slug' => __('Slug'),
        ];
    }
}
