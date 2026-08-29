<?php

namespace App\Http\Requests\Account;

use App\Models\CustomerAddress;
use App\Support\CustomerAddressValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCustomerAddressRequest extends FormRequest
{
    use InteractsWithCustomerAddressInput;

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
        return CustomerAddressValidation::accountRules();
    }

    public function withValidator(Validator $validator): void
    {
        CustomerAddressValidation::validateCityBelongsToGovernorate(
            $validator,
            $this->input('governorate_id'),
            $this->input('city_id'),
        );
    }

    /**
     * @return array{label: ?string, recipient_name: string, phone: string, governorate_id: int, city_id: int, line1: string, line2: ?string, notes: ?string, is_default: bool}
     */
    public function validatedAddress(): array
    {
        $validated = $this->validated();

        return [
            'label' => $validated['label'] ?? null,
            'recipient_name' => (string) $validated['recipient_name'],
            'phone' => (string) $validated['phone'],
            'governorate_id' => (int) $validated['governorate_id'],
            'city_id' => (int) $validated['city_id'],
            'line1' => (string) $validated['line1'],
            'line2' => $validated['line2'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_default' => $this->wantsDefaultAddress(),
        ];
    }
}
