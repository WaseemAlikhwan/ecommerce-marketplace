<?php

namespace App\Http\Requests\Admin;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttributeValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $attribute = $this->route('attribute');
        $value = $this->route('attribute_value');

        return $attribute instanceof Attribute
            && $value instanceof AttributeValue
            && $value->attribute_id === $attribute->id
            && ($this->user()?->can('update', $value) ?? false);
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
        /** @var AttributeValue $value */
        $value = $this->route('attribute_value');

        return [
            'code' => [
                'required',
                'string',
                'max:120',
                'alpha_dash:ascii',
                Rule::unique('attribute_values', 'code')
                    ->where(fn ($query) => $query->where('attribute_id', $attribute->id))
                    ->ignore($value->id),
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
