@php
    $isEdit = isset($brand);
    $action = $isEdit ? route('admin.brands.update', $brand) : route('admin.brands.store');
    $title = $isEdit ? __('Edit brand') : __('Add brand');
@endphp

<x-admin-layout :title="$title">
    <x-ui.page-header :title="$title" :description="__('Arabic and English names are required. Slug is canonical and optional on create.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Admin'), 'href' => route('admin.dashboard')],
                ['label' => __('Catalog'), 'href' => route('admin.catalog')],
                ['label' => __('Brands'), 'href' => route('admin.brands.index')],
                ['label' => $title],
            ]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ $action }}" class="max-w-3xl space-y-6 border border-line bg-surface p-5">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <x-input-label for="translations_ar_name" :value="__('Arabic name')" />
                <x-text-input id="translations_ar_name" name="translations[ar][name]" type="text" :value="old('translations.ar.name', $translations['ar']?->name ?? '')" required dir="rtl" />
                <x-input-error :messages="$errors->get('translations.ar.name')" />
            </div>
            <div>
                <x-input-label for="translations_en_name" :value="__('English name')" />
                <x-text-input id="translations_en_name" name="translations[en][name]" type="text" :value="old('translations.en.name', $translations['en']?->name ?? '')" required dir="ltr" />
                <x-input-error :messages="$errors->get('translations.en.name')" />
            </div>
            <div>
                <x-input-label for="translations_ar_description" :value="__('Arabic description')" />
                <textarea id="translations_ar_description" name="translations[ar][description]" rows="4" class="ds-input" dir="rtl">{{ old('translations.ar.description', $translations['ar']?->description ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('translations.ar.description')" />
            </div>
            <div>
                <x-input-label for="translations_en_description" :value="__('English description')" />
                <textarea id="translations_en_description" name="translations[en][description]" rows="4" class="ds-input" dir="ltr">{{ old('translations.en.description', $translations['en']?->description ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('translations.en.description')" />
            </div>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <x-input-label for="slug" :value="__('Slug')" />
                <x-text-input id="slug" name="slug" type="text" :value="old('slug', $brand->slug ?? '')" dir="ltr" :required="$isEdit" />
                <p class="mt-1 text-caption text-ink-muted">{{ $isEdit ? __('Changing the slug is explicit and must stay unique.') : __('Leave blank to generate from the English name.') }}</p>
                <x-input-error :messages="$errors->get('slug')" />
            </div>
            <div>
                <x-input-label for="is_active" :value="__('Status')" />
                <x-ui.select id="is_active" name="is_active" class="w-full py-2">
                    <option value="1" @selected((string) old('is_active', ($brand->is_active ?? true) ? '1' : '0') === '1')>{{ __('Active') }}</option>
                    <option value="0" @selected((string) old('is_active', ($brand->is_active ?? true) ? '1' : '0') === '0')>{{ __('Inactive') }}</option>
                </x-ui.select>
                <x-input-error :messages="$errors->get('is_active')" />
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <x-primary-button>{{ $isEdit ? __('Save changes') : __('Create brand') }}</x-primary-button>
            <x-ui.button :href="route('admin.brands.index')" variant="ghost">{{ __('Cancel') }}</x-ui.button>
        </div>
    </form>
</x-admin-layout>
