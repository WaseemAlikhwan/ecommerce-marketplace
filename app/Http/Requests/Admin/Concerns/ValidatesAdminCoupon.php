<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesAdminCoupon
{
    protected function prepareCouponPayload(): void
    {
        $code = $this->input('code');
        $scope = $this->input('scope');

        $nullableInts = [
            'vendor_id',
            'max_discount_amount_minor',
            'global_usage_limit',
            'per_user_usage_limit',
        ];

        $merge = [
            'is_active' => $this->boolean('is_active'),
            'product_ids' => array_values(array_filter(
                array_map('intval', (array) $this->input('product_ids', [])),
                fn (int $id): bool => $id > 0,
            )),
            'category_ids' => array_values(array_filter(
                array_map('intval', (array) $this->input('category_ids', [])),
                fn (int $id): bool => $id > 0,
            )),
        ];

        if (is_string($code)) {
            $merge['code'] = strtoupper(trim($code));
        }

        if ($scope === CouponScope::Platform->value) {
            $merge['vendor_id'] = null;
        }

        foreach ($nullableInts as $field) {
            if ($this->exists($field) && $this->input($field) === '') {
                $merge[$field] = null;
            }
        }

        foreach (['starts_at', 'ends_at'] as $field) {
            if ($this->exists($field) && $this->input($field) === '') {
                $merge[$field] = null;
            }
        }

        if ($this->exists('min_eligible_amount_minor') && $this->input('min_eligible_amount_minor') === '') {
            $merge['min_eligible_amount_minor'] = 0;
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    protected function couponRules(?int $ignoreCouponId = null): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('coupons', 'code')->ignore($ignoreCouponId),
            ],
            'scope' => ['required', 'string', Rule::in(array_column(CouponScope::cases(), 'value'))],
            'vendor_id' => [
                Rule::requiredIf(fn (): bool => $this->input('scope') === CouponScope::Vendor->value),
                'nullable',
                'integer',
                'exists:vendors,id',
            ],
            'type' => ['required', 'string', Rule::in(array_column(CouponType::cases(), 'value'))],
            'value' => [
                'required',
                'integer',
                'min:1',
                Rule::when(
                    $this->input('type') === CouponType::Percent->value,
                    ['max:100'],
                ),
            ],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'min_eligible_amount_minor' => ['required', 'integer', 'min:0'],
            'max_discount_amount_minor' => ['nullable', 'integer', 'min:1'],
            'global_usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function couponAttributes(): array
    {
        return [
            'code' => __('Coupon code'),
            'scope' => __('Scope'),
            'vendor_id' => __('Vendor'),
            'type' => __('Discount type'),
            'value' => __('Value'),
            'currency_code' => __('Currency'),
            'starts_at' => __('Starts at'),
            'ends_at' => __('Ends at'),
            'min_eligible_amount_minor' => __('Minimum eligible amount (minor units)'),
            'max_discount_amount_minor' => __('Maximum discount (minor units)'),
            'global_usage_limit' => __('Global usage limit'),
            'per_user_usage_limit' => __('Per-user usage limit'),
            'is_active' => __('Status'),
            'product_ids' => __('Restricted products'),
            'product_ids.*' => __('Restricted product'),
            'category_ids' => __('Restricted categories'),
            'category_ids.*' => __('Restricted category'),
        ];
    }

    protected function afterCouponValidation(Validator $validator): void
    {
        if ($this->input('scope') === CouponScope::Platform->value && filled($this->input('vendor_id'))) {
            $validator->errors()->add('vendor_id', __('Platform coupons cannot be tied to a vendor.'));
        }
    }
}
