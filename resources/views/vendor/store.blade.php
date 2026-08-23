<x-vendor-layout :title="__('Store profile')">
    <x-slot name="header">{{ __('Store profile') }}</x-slot>

    <x-ui.page-header :title="__('Store profile')" :description="__('Update the identity of your single store.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[['label' => __('Vendor'), 'href' => route('vendor.dashboard')], ['label' => __('Store profile')]]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('vendor.store.update') }}" enctype="multipart/form-data" class="max-w-xl space-y-5 border border-line bg-surface p-5">
        @csrf
        @method('PUT')
        <div>
            <x-input-label for="name" :value="__('Store name')" />
            <x-text-input id="name" name="name" type="text" :value="old('name', $store->name)" required />
            <x-input-error :messages="$errors->get('name')" />
        </div>
        <div>
            <x-input-label for="description" :value="__('Description')" />
            <textarea id="description" name="description" rows="5" class="ds-input">{{ old('description', $store->description) }}</textarea>
            <x-input-error :messages="$errors->get('description')" />
        </div>
        <div>
            <x-input-label for="contact_email" :value="__('Contact email')" />
            <x-text-input id="contact_email" name="contact_email" type="email" :value="old('contact_email', $store->contact_email)" />
            <x-input-error :messages="$errors->get('contact_email')" />
        </div>
        <div>
            <x-input-label for="contact_phone" :value="__('Contact phone')" />
            <x-text-input id="contact_phone" name="contact_phone" type="text" :value="old('contact_phone', $store->contact_phone)" dir="ltr" />
            <x-input-error :messages="$errors->get('contact_phone')" />
        </div>
        <div>
            <x-input-label for="default_currency_code" :value="__('Default currency')" />
            <x-ui.select id="default_currency_code" name="default_currency_code" class="w-full py-2" required>
                @foreach ($currencies as $currency)
                    <option value="{{ $currency->code }}" @selected(old('default_currency_code', $store->default_currency_code) === $currency->code)>
                        {{ $currency->label() }}
                    </option>
                @endforeach
            </x-ui.select>
            <p class="mt-1 text-caption text-ink-muted">{{ __('Used as the starting currency for new products later. Checkout conversion rules stay open.') }}</p>
            <x-input-error :messages="$errors->get('default_currency_code')" />
        </div>
        <div>
            <x-input-label for="logo" :value="__('Logo')" />
            @if ($store->logoUrl())
                <img src="{{ $store->logoUrl() }}" alt="" class="mb-2 h-16 w-16 object-cover">
            @endif
            <input id="logo" name="logo" type="file" accept="image/*" class="ds-input">
            <x-input-error :messages="$errors->get('logo')" />
        </div>
        <div>
            <x-input-label for="banner" :value="__('Banner')" />
            @if ($store->bannerUrl())
                <img src="{{ $store->bannerUrl() }}" alt="" class="mb-2 h-20 w-full max-w-md object-cover">
            @endif
            <input id="banner" name="banner" type="file" accept="image/*" class="ds-input">
            <x-input-error :messages="$errors->get('banner')" />
        </div>
        <x-primary-button>{{ __('Save') }}</x-primary-button>
    </form>
</x-vendor-layout>
