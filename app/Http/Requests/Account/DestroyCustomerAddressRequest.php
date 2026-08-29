<?php

namespace App\Http\Requests\Account;

use App\Models\CustomerAddress;
use Illuminate\Foundation\Http\FormRequest;

class DestroyCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CustomerAddress|null $address */
        $address = $this->route('customerAddress');

        return $address instanceof CustomerAddress
            && ($this->user()?->can('delete', $address) ?? false);
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
