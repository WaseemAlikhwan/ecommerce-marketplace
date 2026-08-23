<?php

namespace App\Http\Requests\Storefront;

use App\Models\City;
use App\Models\CustomerAddress;
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

        return [
            'address_mode' => ['required', Rule::in(['existing', 'new'])],
            'address_id' => [
                'nullable',
                'required_if:address_mode,existing',
                'integer',
                Rule::exists('customer_addresses', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            'label' => ['nullable', 'string', 'max:80'],
            'recipient_name' => ['nullable', 'required_if:address_mode,new', 'string', 'max:120'],
            'phone' => ['nullable', 'required_if:address_mode,new', 'string', 'max:32'],
            'governorate_id' => [
                'nullable',
                'required_if:address_mode,new',
                'integer',
                Rule::exists('governorates', 'id')->where(fn ($q) => $q->where('country_code', 'SY')->where('is_active', true)),
            ],
            'city_id' => [
                'nullable',
                'required_if:address_mode,new',
                'integer',
                Rule::exists('cities', 'id')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'line1' => ['nullable', 'required_if:address_mode,new', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('address_mode') !== 'new') {
                return;
            }

            $governorateId = (int) $this->input('governorate_id');
            $cityId = (int) $this->input('city_id');
            if ($governorateId < 1 || $cityId < 1) {
                return;
            }

            $cityOk = City::query()
                ->whereKey($cityId)
                ->where('governorate_id', $governorateId)
                ->where('is_active', true)
                ->exists();

            if (! $cityOk) {
                $validator->errors()->add('city_id', __('The selected city does not belong to that governorate.'));
            }
        });
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

        $makeDefault = $this->boolean('is_default')
            || ! CustomerAddress::query()->where('user_id', $user->id)->exists();

        if ($makeDefault) {
            CustomerAddress::query()
                ->where('user_id', $user->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        return CustomerAddress::query()->create([
            'user_id' => $user->id,
            'label' => $this->input('label'),
            'recipient_name' => $this->input('recipient_name'),
            'phone' => $this->input('phone'),
            'governorate_id' => (int) $this->input('governorate_id'),
            'city_id' => (int) $this->input('city_id'),
            'line1' => $this->input('line1'),
            'line2' => $this->input('line2'),
            'notes' => $this->input('notes'),
            'is_default' => $makeDefault,
        ]);
    }
}
