<?php

namespace App\Http\Requests\Account;

use App\Models\CustomerAddress;
use Illuminate\Foundation\Http\FormRequest;

class SetDefaultCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CustomerAddress|null $address */
        $address = $this->route('customerAddress');

        return $address instanceof CustomerAddress
            && ($this->user()?->can('update', $address) ?? false);
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
