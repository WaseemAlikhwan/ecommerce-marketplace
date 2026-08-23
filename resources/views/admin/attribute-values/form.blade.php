@php
    $isEdit = isset($value) && $value;
    $action = $isEdit
        ? route('admin.attribute-values.update', [$attribute, $value])
        : route('admin.attribute-values.store', $attribute);
    $title = $isEdit ? __('Edit value') : __('Add value');
@endphp

<x-admin-layout :title="$title">
    <x-ui.page-header :title="$title" :description="__('Arabic and English names are required. Code is stable within this attribute.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Admin'), 'href' => route('admin.dashboard')],
                ['label' => __('Catalog'), 'href' => route('admin.catalog')],
                ['label' => __('Attributes'), 'href' => route('admin.attributes.index')],
                ['label' => $attribute->name(), 'href' => route('admin.attributes.show', $attribute)],
                ['label' => $title],
            ]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    <p class="mb-4 text-sm text-ink-muted">
        {{ __('Attribute') }}: <span class="text-ink">{{ $attribute->name() }}</span>
        <span dir="ltr" class="text-caption text-ink-muted">({{ $attribute->code }})</span>
    </p>

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
                <x-input-label for="code" :value="__('Code')" />
                <x-text-input id="code" name="code" type="text" :value="old('code', $value->code ?? '')" dir="ltr" :required="$isEdit" />
                <p class="mt-1 text-caption text-ink-muted">
                    {{ $isEdit ? __('Changing the code is explicit and must stay unique within this attribute.') : __('Leave blank to generate from the English name.') }}
                </p>
                <x-input-error :messages="$errors->get('code')" />
            </div>
            <div>
                <x-input-label for="position" :value="__('Position')" />
                <x-text-input id="position" name="position" type="number" min="0" :value="old('position', $value->position ?? 0)" dir="ltr" />
                <x-input-error :messages="$errors->get('position')" />
            </div>
            <div>
                <x-input-label for="is_active" :value="__('Status')" />
                <x-ui.select id="is_active" name="is_active" class="w-full py-2">
                    <option value="1" @selected((string) old('is_active', ($value->is_active ?? true) ? '1' : '0') === '1')>{{ __('Active') }}</option>
                    <option value="0" @selected((string) old('is_active', ($value->is_active ?? true) ? '1' : '0') === '0')>{{ __('Inactive') }}</option>
                </x-ui.select>
                <x-input-error :messages="$errors->get('is_active')" />
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <x-primary-button>{{ $isEdit ? __('Save changes') : __('Create value') }}</x-primary-button>
            <x-ui.button :href="route('admin.attributes.show', $attribute)" variant="ghost">{{ __('Cancel') }}</x-ui.button>
        </div>
    </form>
</x-admin-layout>
