<?php

namespace App\Support;

use App\Models\City;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class CustomerAddressValidation
{
    /**
     * Field rules for account create/update (always required core fields).
     *
     * @return array<string, mixed>
     */
    public static function accountRules(): array
    {
        return self::coreFieldRules(required: true);
    }

    /**
     * Field rules for checkout inline new address (required only when address_mode=new).
     *
     * @return array<string, mixed>
     */
    public static function checkoutNewAddressRules(): array
    {
        return [
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

    public static function validateCityBelongsToGovernorate(Validator $validator, mixed $governorateId, mixed $cityId): void
    {
        $validator->after(function (Validator $validator) use ($governorateId, $cityId): void {
            $govId = (int) $governorateId;
            $city = (int) $cityId;
            if ($govId < 1 || $city < 1) {
                return;
            }

            $cityOk = City::query()
                ->whereKey($city)
                ->where('governorate_id', $govId)
                ->where('is_active', true)
                ->exists();

            if (! $cityOk) {
                $validator->errors()->add('city_id', __('The selected city does not belong to that governorate.'));
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function coreFieldRules(bool $required): array
    {
        $req = $required ? 'required' : 'nullable';

        return [
            'label' => ['nullable', 'string', 'max:80'],
            'recipient_name' => [$req, 'string', 'max:120'],
            'phone' => [$req, 'string', 'max:32'],
            'governorate_id' => [
                $req,
                'integer',
                Rule::exists('governorates', 'id')->where(fn ($q) => $q->where('country_code', 'SY')->where('is_active', true)),
            ],
            'city_id' => [
                $req,
                'integer',
                Rule::exists('cities', 'id')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'line1' => [$req, 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
