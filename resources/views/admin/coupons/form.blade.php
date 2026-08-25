@php
    $isEdit = isset($coupon);
    $action = $isEdit ? route('admin.coupons.update', $coupon) : route('admin.coupons.store');
    $title = $isEdit ? __('Edit coupon') : __('Add coupon');
    $selectedProducts = collect(old('product_ids', $isEdit ? $coupon->products->pluck('id')->all() : []))->map(fn ($id) => (string) $id);
    $selectedCategories = collect(old('category_ids', $isEdit ? $coupon->categories->pluck('id')->all() : []))->map(fn ($id) => (string) $id);
    $defaultScope = old('scope', $coupon->scope->value ?? 'platform');
@endphp

<x-admin-layout :title="$title">
    <x-ui.page-header :title="$title" :description="__('Define code, scope, type, schedule, limits, and optional product or category restrictions. Money fields use integer minor units.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Admin'), 'href' => route('admin.dashboard')],
                ['label' => __('Coupons'), 'href' => route('admin.coupons.index')],
                ['label' => $title],
            ]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    <form
        method="POST"
        action="{{ $action }}"
        class="max-w-3xl space-y-6 border border-line bg-surface p-5"
        x-data="{ scope: @js($defaultScope) }"
    >
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <x-input-label for="code" :value="__('Coupon code')" />
                <x-text-input id="code" name="code" type="text" :value="old('code', $coupon->code ?? '')" required dir="ltr" maxlength="64" />
                <p class="mt-1 text-caption text-ink-muted">{{ __('Codes are stored uppercase and must be unique.') }}</p>
                <x-input-error :messages="$errors->get('code')" />
            </div>
            <div>
                <x-input-label for="is_active" :value="__('Status')" />
                <x-ui.select id="is_active" name="is_active" class="w-full py-2">
                    <option value="1" @selected((string) old('is_active', ($coupon->is_active ?? true) ? '1' : '0') === '1')>{{ __('Active') }}</option>
                    <option value="0" @selected((string) old('is_active', ($coupon->is_active ?? true) ? '1' : '0') === '0')>{{ __('Inactive') }}</option>
                </x-ui.select>
                <x-input-error :messages="$errors->get('is_active')" />
            </div>
            <div>
                <x-input-label for="scope" :value="__('Scope')" />
                <x-ui.select id="scope" name="scope" class="w-full py-2" x-model="scope" required>
                    @foreach ($scopes as $scopeOption)
                        <option value="{{ $scopeOption->value }}" @selected($defaultScope === $scopeOption->value)>
                            {{ $scopeOption->value === 'platform' ? __('Platform') : __('Vendor') }}
                        </option>
                    @endforeach
                </x-ui.select>
                <x-input-error :messages="$errors->get('scope')" />
            </div>
            <div x-show="scope === 'vendor'" x-cloak>
                <x-input-label for="vendor_id" :value="__('Vendor')" />
                <x-ui.select id="vendor_id" name="vendor_id" class="w-full py-2">
                    <option value="">{{ __('Select vendor') }}</option>
                    @foreach ($vendors as $vendor)
                        <option value="{{ $vendor->id }}" @selected((string) old('vendor_id', $coupon->vendor_id ?? '') === (string) $vendor->id)>
                            #{{ $vendor->id }} — {{ $vendor->store?->name ?? __('No store') }}
                        </option>
                    @endforeach
                </x-ui.select>
                <x-input-error :messages="$errors->get('vendor_id')" />
            </div>
            <div>
                <x-input-label for="type" :value="__('Discount type')" />
                <x-ui.select id="type" name="type" class="w-full py-2" required>
                    @foreach ($types as $typeOption)
                        <option value="{{ $typeOption->value }}" @selected((string) old('type', $coupon->type->value ?? 'percent') === $typeOption->value)>
                            {{ $typeOption->value === 'percent' ? __('Percent') : __('Fixed amount') }}
                        </option>
                    @endforeach
                </x-ui.select>
                <x-input-error :messages="$errors->get('type')" />
            </div>
            <div>
                <x-input-label for="value" :value="__('Value')" />
                <x-text-input id="value" name="value" type="number" min="1" :value="old('value', $coupon->value ?? '')" required dir="ltr" />
                <p class="mt-1 text-caption text-ink-muted">{{ __('Percent: 1–100. Fixed: amount in minor units.') }}</p>
                <x-input-error :messages="$errors->get('value')" />
            </div>
            <div>
                <x-input-label for="currency_code" :value="__('Currency')" />
                <x-ui.select id="currency_code" name="currency_code" class="w-full py-2" required>
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->code }}" @selected((string) old('currency_code', $coupon->currency_code ?? 'SYP') === $currency->code)>
                            {{ $currency->label() }}
                        </option>
                    @endforeach
                </x-ui.select>
                <x-input-error :messages="$errors->get('currency_code')" />
            </div>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <x-input-label for="starts_at" :value="__('Starts at')" />
                <x-text-input id="starts_at" name="starts_at" type="datetime-local" :value="old('starts_at', isset($coupon) && $coupon->starts_at ? $coupon->starts_at->format('Y-m-d\\TH:i') : '')" dir="ltr" />
                <x-input-error :messages="$errors->get('starts_at')" />
            </div>
            <div>
                <x-input-label for="ends_at" :value="__('Ends at')" />
                <x-text-input id="ends_at" name="ends_at" type="datetime-local" :value="old('ends_at', isset($coupon) && $coupon->ends_at ? $coupon->ends_at->format('Y-m-d\\TH:i') : '')" dir="ltr" />
                <x-input-error :messages="$errors->get('ends_at')" />
            </div>
            <div>
                <x-input-label for="min_eligible_amount_minor" :value="__('Minimum eligible amount (minor units)')" />
                <x-text-input id="min_eligible_amount_minor" name="min_eligible_amount_minor" type="number" min="0" :value="old('min_eligible_amount_minor', $coupon->min_eligible_amount_minor ?? 0)" required dir="ltr" />
                <x-input-error :messages="$errors->get('min_eligible_amount_minor')" />
            </div>
            <div>
                <x-input-label for="max_discount_amount_minor" :value="__('Maximum discount (minor units)')" />
                <x-text-input id="max_discount_amount_minor" name="max_discount_amount_minor" type="number" min="1" :value="old('max_discount_amount_minor', $coupon->max_discount_amount_minor ?? '')" dir="ltr" />
                <x-input-error :messages="$errors->get('max_discount_amount_minor')" />
            </div>
            <div>
                <x-input-label for="global_usage_limit" :value="__('Global usage limit')" />
                <x-text-input id="global_usage_limit" name="global_usage_limit" type="number" min="1" :value="old('global_usage_limit', $coupon->global_usage_limit ?? '')" dir="ltr" />
                <x-input-error :messages="$errors->get('global_usage_limit')" />
            </div>
            <div>
                <x-input-label for="per_user_usage_limit" :value="__('Per-user usage limit')" />
                <x-text-input id="per_user_usage_limit" name="per_user_usage_limit" type="number" min="1" :value="old('per_user_usage_limit', $coupon->per_user_usage_limit ?? '')" dir="ltr" />
                <x-input-error :messages="$errors->get('per_user_usage_limit')" />
            </div>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <x-input-label for="category_ids" :value="__('Restricted categories')" />
                <select id="category_ids" name="category_ids[]" multiple size="8" class="ds-input w-full py-2">
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected($selectedCategories->contains((string) $category->id))>
                            {{ $category->name() }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-caption text-ink-muted">{{ __('Leave empty for no category restriction. Hold Ctrl/Cmd to multi-select.') }}</p>
                <x-input-error :messages="$errors->get('category_ids')" />
                <x-input-error :messages="$errors->get('category_ids.*')" />
            </div>
            <div>
                <x-input-label for="product_ids" :value="__('Restricted products')" />
                <select id="product_ids" name="product_ids[]" multiple size="8" class="ds-input w-full py-2">
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected($selectedProducts->contains((string) $product->id))>
                            #{{ $product->id }} — {{ $product->name() }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-caption text-ink-muted">{{ __('Leave empty for no product restriction. Recent products only; IDs only—no SKU or stock quantity.') }}</p>
                <x-input-error :messages="$errors->get('product_ids')" />
                <x-input-error :messages="$errors->get('product_ids.*')" />
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <x-primary-button>{{ $isEdit ? __('Save changes') : __('Create coupon') }}</x-primary-button>
            <x-ui.button :href="route('admin.coupons.index')" variant="ghost">{{ __('Cancel') }}</x-ui.button>
            @if ($isEdit)
                <x-ui.button :href="route('admin.coupons.show', $coupon)" variant="ghost">{{ __('View') }}</x-ui.button>
            @endif
        </div>
    </form>
</x-admin-layout>
