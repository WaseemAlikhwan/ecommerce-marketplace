@php
    $isEdit = isset($product) && $product;
    $readOnly = $isEdit && ! $canEdit;
@endphp

<x-ui.card>
    <fieldset>
        <legend class="mb-4 text-heading-3">{{ __('Default variant') }}</legend>
        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <x-input-label for="sku" :value="__('SKU')" />
                <x-text-input id="sku" name="sku" type="text" :value="old('sku', $sku)" dir="ltr" x-bind:required="type === 'simple'" :disabled="$readOnly" />
                <x-input-error :messages="$errors->get('sku')" />
            </div>
            <div>
                <x-input-label for="quantity" :value="__('Quantity')" />
                <x-text-input id="quantity" name="quantity" type="number" min="0" step="1" :value="old('quantity', $quantity)" dir="ltr" x-bind:required="type === 'simple'" :disabled="$readOnly" />
                <x-input-error :messages="$errors->get('quantity')" />
            </div>
            <div>
                <x-input-label for="price" :value="__('Price')" />
                <x-text-input id="price" name="price" type="text" :value="old('price', $price)" dir="ltr" x-bind:required="type === 'simple'" :disabled="$readOnly" />
                <p class="mt-1 text-caption text-ink-muted">{{ __('Use whole numbers for SYP, or up to two decimals for USD.') }}</p>
                <x-input-error :messages="$errors->get('price')" />
            </div>
            <div>
                <x-input-label for="compare_at_price" :value="__('Compare-at price')" />
                <x-text-input id="compare_at_price" name="compare_at_price" type="text" :value="old('compare_at_price', $compare_at_price)" dir="ltr" :disabled="$readOnly" />
                <p class="mt-1 text-caption text-ink-muted">{{ __('Optional. Must be greater than the selling price.') }}</p>
                <x-input-error :messages="$errors->get('compare_at_price')" />
            </div>
        </div>
    </fieldset>
</x-ui.card>
