<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesAdminCoupon;
use App\Models\Coupon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCouponRequest extends FormRequest
{
    use ValidatesAdminCoupon;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Coupon::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareCouponPayload();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->couponRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->couponAttributes();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator): mixed => $this->afterCouponValidation($validator));
    }
}
