@php
    $isEdit = isset($product) && $product;
    $action = $isEdit ? route('vendor.products.update', $product) : route('vendor.products.store');
    $title = $isEdit ? __('Edit product') : __('Add product');
    $readOnly = $isEdit && ! $canEdit;
    $description = $isEdit
        ? __('Update product details, translations, gallery, and variants. Review publication readiness before publishing.')
        : __('Create a simple or variable product draft for your store. Finish details on the edit page, then publish when ready.');
    $hasReadiness = $isEdit && isset($readinessBootstrap) && is_array($readinessBootstrap);
@endphp

<x-vendor-layout :title="$title">
    <x-slot name="header">{{ $title }}</x-slot>

    <x-ui.page-header :title="$title" :description="$description">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Vendor'), 'href' => route('vendor.dashboard')],
                ['label' => __('Products'), 'href' => route('vendor.products')],
                ['label' => $title],
            ]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert tone="danger" class="mb-6" :title="__('Please fix the highlighted fields.')">
            <ul class="list-disc ps-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    @if ($isEdit)
        <div class="mb-6 flex flex-wrap items-center gap-2">
            <x-ui.badge :tone="$product->status->badgeTone()">{{ $product->status->label() }}</x-ui.badge>
            <x-ui.badge :tone="$product->type === \App\Enums\ProductType::Variable ? 'brand' : 'neutral'">{{ $product->type->label() }}</x-ui.badge>
            @if ($product->hasMissingTranslation())
                <x-ui.badge tone="warning">{{ __('Missing translation') }}</x-ui.badge>
            @endif
        </div>
    @endif

    <div
        @class([
            'gap-8',
            'lg:grid lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start' => $hasReadiness,
        ])
        @if ($hasReadiness)
            x-data="vendorProductReadiness(@js($readinessBootstrap))"
        @endif
    >
        <div class="min-w-0 space-y-6">
            <form
                method="POST"
                action="{{ $action }}"
                class="space-y-6"
                id="vendor-product-form"
                x-data="vendorProductForm(@js($matrixBootstrap))"
                @if ($readOnly) aria-disabled="true" @endif
            >
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                @include('vendor.products.partials.type-select')

                <x-ui.card>
                    <fieldset id="product-details" class="scroll-mt-28" tabindex="-1">
                        <legend class="mb-4 text-heading-3">{{ __('Product details') }}</legend>
                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <x-input-label for="slug" :value="__('Slug')" />
                                <x-text-input id="slug" name="slug" type="text" :value="old('slug', $product->slug ?? '')" dir="ltr" :required="$isEdit" :disabled="$readOnly" />
                                <p class="mt-1 text-caption text-ink-muted">
                                    {{ $isEdit ? __('Changing the slug is explicit and must stay unique.') : __('Leave blank to generate from the English name, or a stable unique fallback.') }}
                                </p>
                                <x-input-error :messages="$errors->get('slug')" />
                            </div>
                            <div>
                                <x-input-label for="currency_code" :value="__('Currency')" />
                                <x-ui.select id="currency_code" name="currency_code" class="w-full py-2" x-model="currencyCode" :disabled="$readOnly">
                                    @foreach ($currencies as $currency)
                                        <option value="{{ $currency->code }}" @selected(old('currency_code', $isEdit ? $product->currency_code : $defaultCurrencyCode) === $currency->code)>
                                            {{ $currency->label() }}@if ($currency->is_inactive_current ?? false) — {{ __('Inactive — current selection') }}@endif
                                        </option>
                                    @endforeach
                                </x-ui.select>
                                <p class="mt-1 text-caption text-warning" x-cloak x-show="currencyChanged">{{ __('Changing currency does not convert existing prices. Re-enter amounts for the new currency.') }}</p>
                                <x-input-error :messages="$errors->get('currency_code')" />
                            </div>
                            <div>
                                <x-input-label for="category_id" :value="__('Category')" />
                                <x-ui.select id="category_id" name="category_id" class="w-full py-2" :disabled="$readOnly">
                                    <option value="">{{ __('No category (draft)') }}</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id ?? '') === (string) $category->id)>
                                            {{ $category->option_label }}@if ($category->is_inactive_current ?? false) — {{ __('Inactive — current selection') }}@endif
                                        </option>
                                    @endforeach
                                </x-ui.select>
                                <x-input-error :messages="$errors->get('category_id')" />
                            </div>
                            <div>
                                <x-input-label for="brand_id" :value="__('Brand')" />
                                <x-ui.select id="brand_id" name="brand_id" class="w-full py-2" :disabled="$readOnly">
                                    <option value="">{{ __('No brand') }}</option>
                                    @foreach ($brands as $brand)
                                        <option value="{{ $brand->id }}" @selected((string) old('brand_id', $product->brand_id ?? '') === (string) $brand->id)>
                                            {{ $brand->name() }}@if ($brand->is_inactive_current ?? false) — {{ __('Inactive — current selection') }}@endif
                                        </option>
                                    @endforeach
                                </x-ui.select>
                                <x-input-error :messages="$errors->get('brand_id')" />
                            </div>
                        </div>
                    </fieldset>
                </x-ui.card>

                <x-ui.card>
                    <fieldset id="product-content" class="scroll-mt-28" tabindex="-1">
                        <legend class="mb-4 text-heading-3">{{ __('Content') }}</legend>
                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <x-input-label for="translations_ar_name" :value="__('Arabic name')" />
                                <x-text-input id="translations_ar_name" name="translations[ar][name]" type="text" :value="old('translations.ar.name', $translations['ar']?->name ?? '')" dir="rtl" :disabled="$readOnly" />
                                <x-input-error :messages="$errors->get('translations.ar.name')" />
                            </div>
                            <div>
                                <x-input-label for="translations_en_name" :value="__('English name')" />
                                <x-text-input id="translations_en_name" name="translations[en][name]" type="text" :value="old('translations.en.name', $translations['en']?->name ?? '')" dir="ltr" :disabled="$readOnly" />
                                <x-input-error :messages="$errors->get('translations.en.name')" />
                            </div>
                            <div>
                                <x-input-label for="translations_ar_short" :value="__('Arabic short description')" />
                                <textarea id="translations_ar_short" name="translations[ar][short_description]" rows="3" class="ds-input" dir="rtl" @disabled($readOnly)>{{ old('translations.ar.short_description', $translations['ar']?->short_description ?? '') }}</textarea>
                                <x-input-error :messages="$errors->get('translations.ar.short_description')" />
                            </div>
                            <div>
                                <x-input-label for="translations_en_short" :value="__('English short description')" />
                                <textarea id="translations_en_short" name="translations[en][short_description]" rows="3" class="ds-input" dir="ltr" @disabled($readOnly)>{{ old('translations.en.short_description', $translations['en']?->short_description ?? '') }}</textarea>
                                <x-input-error :messages="$errors->get('translations.en.short_description')" />
                            </div>
                            <div>
                                <x-input-label for="translations_ar_description" :value="__('Arabic description')" />
                                <textarea id="translations_ar_description" name="translations[ar][description]" rows="5" class="ds-input" dir="rtl" @disabled($readOnly)>{{ old('translations.ar.description', $translations['ar']?->description ?? '') }}</textarea>
                                <x-input-error :messages="$errors->get('translations.ar.description')" />
                            </div>
                            <div>
                                <x-input-label for="translations_en_description" :value="__('English description')" />
                                <textarea id="translations_en_description" name="translations[en][description]" rows="5" class="ds-input" dir="ltr" @disabled($readOnly)>{{ old('translations.en.description', $translations['en']?->description ?? '') }}</textarea>
                                <x-input-error :messages="$errors->get('translations.en.description')" />
                            </div>
                        </div>
                        <x-input-error class="mt-3" :messages="$errors->get('translations')" />
                        <p class="mt-3 text-caption text-ink-muted">{{ __('A draft needs at least an Arabic or English name. Both languages are required before publishing.') }}</p>
                    </fieldset>
                </x-ui.card>

                <div id="product-variants" class="scroll-mt-28" tabindex="-1" x-cloak x-show="type === 'simple'">
                    @include('vendor.products.partials.simple-variant')
                </div>

                <div id="product-matrix" class="scroll-mt-28" tabindex="-1" x-cloak x-show="type === 'variable'">
                    @include('vendor.products.partials.variable-matrix')
                </div>

                <div class="flex flex-wrap gap-3">
                    @unless ($readOnly)
                        <x-primary-button>{{ $isEdit ? __('Save changes') : __('Save draft') }}</x-primary-button>
                        @if ($isEdit)
                            <button
                                type="button"
                                class="inline-flex h-11 items-center justify-center gap-2 rounded-sm border border-ink/15 bg-transparent px-5 text-label font-medium text-ink transition hover:bg-canvas focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                                x-cloak
                                x-show="formDirty"
                                data-product-discard
                                x-on:click="discardChanges()"
                            >
                                {{ __('Reload saved version') }}
                            </button>
                        @endif
                    @endunless
                    <x-ui.button :href="route('vendor.products')" variant="ghost">{{ __('Back to products') }}</x-ui.button>
                    @if ($isEdit && $canArchive)
                        <button
                            type="submit"
                            form="archive-product-form"
                            class="ds-link text-sm text-danger"
                            onclick="return confirm(@js(__('Archive this product?')))"
                        >
                            {{ __('Archive') }}
                        </button>
                    @endif
                </div>
            </form>

            @if ($isEdit && $canArchive)
                <form id="archive-product-form" method="POST" action="{{ route('vendor.products.archive', $product) }}" class="hidden">
                    @csrf
                </form>
            @endif

            @if ($isEdit && isset($galleryBootstrap))
                @include('vendor.products.partials.product-gallery')
            @elseif (! $isEdit)
                @include('vendor.products.partials.product-gallery-create-info')
            @endif
        </div>

        @if ($hasReadiness)
            @include('vendor.products.partials.product-readiness')
        @endif
    </div>
</x-vendor-layout>
