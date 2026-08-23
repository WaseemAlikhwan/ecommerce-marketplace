<?php

namespace App\Http\Requests\Admin;

use App\Models\Attribute;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $attribute = $this->route('attribute');

        return $attribute instanceof Attribute
            && ($this->user()?->can('update', $attribute) ?? false);
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
        /** @var Attribute $attribute */
        $attribute = $this->route('attribute');

        return [
            'code' => [
                'required',
                'string',
                'max:120',
                'alpha_dash:ascii',
                Rule::unique('attributes', 'code')->ignore($attribute->id),
            ],
            'position' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'translations' => ['required', 'array'],
            'translations.ar.name' => ['required', 'string', 'max:120'],
            'translations.en.name' => ['required', 'string', 'max:120'],
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
            'code' => __('Code'),
            'position' => __('Position'),
        ];
    }
}
