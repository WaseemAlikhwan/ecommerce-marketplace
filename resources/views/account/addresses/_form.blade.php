@php
    /** @var \App\Models\CustomerAddress|null $address */
    $isEdit = $address !== null;
    $formAction = $isEdit
        ? route('account.addresses.update', $address)
        : route('account.addresses.store');
    $governorateId = old('governorate_id', $isEdit ? $address->governorate_id : '');
    $cityId = old('city_id', $isEdit ? $address->city_id : '');
@endphp

<div
    x-data="{
        governorateId: @js((string) $governorateId),
        citiesByGovernorate: {{ Js::from(collect($governorates)->mapWithKeys(fn ($g) => [(string) $g['id'] => $g['cities']])) }},
        selectedCityId: @js((string) $cityId)
    }"
    class="space-y-4"
>
    <div>
        <x-input-label for="label" :value="__('Label')" />
        <x-text-input id="label" name="label" type="text" class="mt-1 block w-full" :value="old('label', $isEdit ? $address->label : '')" />
        <x-input-error :messages="$errors->get('label')" />
    </div>
    <div>
        <x-input-label for="recipient_name" :value="__('Recipient name')" />
        <x-text-input id="recipient_name" name="recipient_name" type="text" class="mt-1 block w-full" :value="old('recipient_name', $isEdit ? $address->recipient_name : '')" required />
        <x-input-error :messages="$errors->get('recipient_name')" />
    </div>
    <div>
        <x-input-label for="phone" :value="__('Phone')" />
        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $isEdit ? $address->phone : '')" required />
        <x-input-error :messages="$errors->get('phone')" />
    </div>
    <div>
        <x-input-label for="governorate_id" :value="__('Governorate')" />
        <select id="governorate_id" name="governorate_id" class="ds-input mt-1 block w-full" x-model="governorateId" required>
            <option value="">{{ __('Select governorate') }}</option>
            @foreach ($governorates as $governorate)
                <option value="{{ $governorate['id'] }}">{{ $governorate['name'] }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('governorate_id')" />
    </div>
    <div>
        <x-input-label for="city_id" :value="__('City')" />
        <select id="city_id" name="city_id" class="ds-input mt-1 block w-full" x-model="selectedCityId" required>
            <option value="">{{ __('Select city') }}</option>
            <template x-for="city in (citiesByGovernorate[governorateId] || [])" :key="city.id">
                <option :value="city.id" x-text="city.name" :selected="String(city.id) === selectedCityId"></option>
            </template>
        </select>
        <x-input-error :messages="$errors->get('city_id')" />
    </div>
    <div>
        <x-input-label for="line1" :value="__('Address line 1')" />
        <x-text-input id="line1" name="line1" type="text" class="mt-1 block w-full" :value="old('line1', $isEdit ? $address->line1 : '')" required />
        <x-input-error :messages="$errors->get('line1')" />
    </div>
    <div>
        <x-input-label for="line2" :value="__('Address line 2')" />
        <x-text-input id="line2" name="line2" type="text" class="mt-1 block w-full" :value="old('line2', $isEdit ? $address->line2 : '')" />
        <x-input-error :messages="$errors->get('line2')" />
    </div>
    <div>
        <x-input-label for="notes" :value="__('Delivery notes')" />
        <textarea id="notes" name="notes" rows="3" class="ds-input mt-1 block w-full">{{ old('notes', $isEdit ? $address->notes : '') }}</textarea>
        <x-input-error :messages="$errors->get('notes')" />
    </div>
    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="is_default" value="0">
        <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $isEdit ? $address->is_default : false))>
        <span>{{ __('Save as default address') }}</span>
    </label>
    <p class="text-caption text-ink-muted">{{ __('Syria governorate and city only — no area level.') }}</p>
</div>
