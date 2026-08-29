<?php

namespace App\Http\Requests\Storefront;

use App\Models\CustomerAddress;
use App\Services\CustomerAddressService;
use App\Support\CustomerAddressValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PlaceCheckoutOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = (int) $this->user()->id;

        return array_merge([
            'address_mode' => ['required', Rule::in(['existing', 'new'])],
            'address_id' => [
                'nullable',
                'required_if:address_mode,existing',
                'integer',
                Rule::exists('customer_addresses', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
        ], CustomerAddressValidation::checkoutNewAddressRules());
    }

    public function withValidator(Validator $validator): void
    {
        if ($this->input('address_mode') !== 'new') {
            return;
        }

        CustomerAddressValidation::validateCityBelongsToGovernorate(
            $validator,
            $this->input('governorate_id'),
            $this->input('city_id'),
        );
    }

    public function resolveAddress(): CustomerAddress
    {
        $user = $this->user();

        if ($this->input('address_mode') === 'existing') {
            /** @var CustomerAddress $address */
            $address = CustomerAddress::query()
                ->where('user_id', $user->id)
                ->whereKey((int) $this->input('address_id'))
                ->firstOrFail();

            return $address;
        }

        /** @var CustomerAddressService $addresses */
        $addresses = app(CustomerAddressService::class);

        return $addresses->create($user, [
            'label' => $this->input('label'),
            'recipient_name' => (string) $this->input('recipient_name'),
            'phone' => (string) $this->input('phone'),
            'governorate_id' => (int) $this->input('governorate_id'),
            'city_id' => (int) $this->input('city_id'),
            'line1' => (string) $this->input('line1'),
            'line2' => $this->input('line2'),
            'notes' => $this->input('notes'),
            'is_default' => $this->boolean('is_default'),
        ]);
    }
}
