@php
    $isEdit = isset($product) && $product;
    $readOnly = $isEdit && ! $canEdit;
@endphp

<x-ui.card>
    <fieldset>
        <legend class="mb-4 text-heading-3">{{ __('Product type') }}</legend>

        @if ($isEdit)
            <input type="hidden" name="type" value="{{ $product->type->value }}">
            <div class="flex flex-wrap items-center gap-3">
                <x-ui.badge :tone="$product->type === \App\Enums\ProductType::Variable ? 'brand' : 'neutral'">
                    {{ $product->type->label() }}
                </x-ui.badge>
                <p class="text-sm text-ink-muted">{{ __('Product type is locked after creation.') }}</p>
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-2">
                <label class="ds-card cursor-pointer p-4 transition has-[:checked]:border-brand has-[:checked]:ring-2 has-[:checked]:ring-brand/30">
                    <input type="radio" name="type" value="simple" class="sr-only" x-model="type" @disabled($readOnly)>
                    <p class="font-medium">{{ __('Simple product') }}</p>
                    <p class="mt-1 text-sm text-ink-muted">{{ __('One SKU, price, and quantity. Best for items without options.') }}</p>
                </label>
                <label class="ds-card cursor-pointer p-4 transition has-[:checked]:border-brand has-[:checked]:ring-2 has-[:checked]:ring-brand/30">
                    <input type="radio" name="type" value="variable" class="sr-only" x-model="type" @disabled($readOnly)>
                    <p class="font-medium">{{ __('Variable product') }}</p>
                    <p class="mt-1 text-sm text-ink-muted">{{ __('Multiple variants from global attributes such as color or size.') }}</p>
                </label>
            </div>
        @endif

        <x-input-error class="mt-3" :messages="$errors->get('type')" />
    </fieldset>
</x-ui.card>
