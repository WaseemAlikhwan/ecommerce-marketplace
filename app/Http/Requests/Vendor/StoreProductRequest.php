<?php

namespace App\Http\Requests\Vendor;

use App\Http\Requests\Vendor\Concerns\ValidatesVendorProductRequest;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
{
    use ValidatesVendorProductRequest;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeVendorProductInput();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(
            $this->commonProductRules(),
            $this->simpleVariantRules(),
            $this->variableMatrixRules(null, true),
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->afterVendorProductValidation($validator));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->vendorProductAttributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->vendorProductMessages();
    }
}
