<?php

namespace App\Http\Requests\Vendor;

use App\Models\VendorOrder;
use Illuminate\Foundation\Http\FormRequest;

class CancelVendorOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var VendorOrder|null $vendorOrder */
        $vendorOrder = $this->route('vendorOrder');

        return $vendorOrder instanceof VendorOrder
            && ($this->user()?->can('cancel', $vendorOrder) ?? false);
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
        return [];
    }
}
