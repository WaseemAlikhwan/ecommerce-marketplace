<?php

namespace App\Http\Requests;

use App\Models\VendorApplication;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVendorApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', VendorApplication::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
