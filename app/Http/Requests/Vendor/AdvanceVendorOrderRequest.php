<?php

namespace App\Http\Requests\Vendor;

use App\Enums\VendorOrderStatus;
use App\Models\VendorOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdvanceVendorOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var VendorOrder|null $vendorOrder */
        $vendorOrder = $this->route('vendorOrder');

        return $vendorOrder instanceof VendorOrder
            && ($this->user()?->can('advance', $vendorOrder) ?? false);
    }

    protected function failedAuthorization(): void
    {
        abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    VendorOrderStatus::Confirmed->value,
                    VendorOrderStatus::Shipped->value,
                    VendorOrderStatus::Delivered->value,
                ]),
            ],
        ];
    }

    public function targetStatus(): VendorOrderStatus
    {
        return VendorOrderStatus::from((string) $this->validated('status'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => __('Choose a valid order status.'),
            'status.in' => __('Choose a valid order status.'),
        ];
    }
}
