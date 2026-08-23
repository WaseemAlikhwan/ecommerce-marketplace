<?php

namespace App\Http\Requests\Vendor;

use App\Http\Requests\Vendor\Concerns\ValidatesVendorProductRequest;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    use ValidatesVendorProductRequest;

    public function authorize(): bool
    {
        /** @var Product|null $product */
        $product = $this->route('product');

        return $product instanceof Product
            && ($this->user()?->can('update', $product) ?? false);
    }

    protected function prepareForValidation(): void
    {
        /** @var Product|null $product */
        $product = $this->route('product');
        if ($product instanceof Product) {
            $product->loadMissing('defaultVariant');
        }

        $this->normalizeVendorProductInput($product instanceof Product ? $product : null);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');

        return array_merge(
            $this->commonProductRules($product),
            $this->simpleVariantRules($product),
            $this->variableMatrixRules($product, false),
        );
    }

    public function withValidator(Validator $validator): void
    {
        /** @var Product|null $product */
        $product = $this->route('product');

        $validator->after(fn (Validator $validator) => $this->afterVendorProductValidation(
            $validator,
            $product instanceof Product ? $product : null,
        ));
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
        /** @var Product|null $product */
        $product = $this->route('product');

        return $this->vendorProductMessages($product instanceof Product ? $product : null);
    }
}
