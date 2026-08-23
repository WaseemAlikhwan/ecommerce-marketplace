<?php

namespace App\Http\Requests;

use App\Models\Store;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $store = $this->route('store') ?? $this->user()?->vendor?->store;

        return $store instanceof Store
            && ($this->user()?->can('update', $store) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('default_currency_code')) {
            $this->merge([
                'default_currency_code' => strtoupper(trim((string) $this->input('default_currency_code'))),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{8,15}$/'],
            'default_currency_code' => [
                'required',
                'string',
                'size:3',
                Rule::exists('currencies', 'code')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'logo' => ['nullable', 'image', 'max:2048'],
            'banner' => ['nullable', 'image', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'default_currency_code' => __('Default currency'),
        ];
    }
}
