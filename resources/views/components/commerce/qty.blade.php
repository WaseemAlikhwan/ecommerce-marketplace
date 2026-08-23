@props([
    'name' => 'quantity',
    'value' => 1,
    'min' => 1,
    'id' => null,
])

@php
    $fieldId = $id ?? 'qty-'.uniqid();
    $minValue = max(0, (int) $min);
    $current = max($minValue, (int) $value);
@endphp

<div {{ $attributes->class('inline-flex h-12 items-center border border-line bg-surface') }} x-data="{ qty: {{ $current }}, min: {{ $minValue }} }">
    <button type="button" class="h-12 w-11 text-lg text-ink-muted transition hover:text-ink" @click="qty = Math.max(min, qty - 1)" aria-label="{{ __('Decrease') }}">−</button>
    <input
        id="{{ $fieldId }}"
        type="number"
        name="{{ $name }}"
        :value="qty"
        x-model.number="qty"
        min="{{ $minValue }}"
        class="h-12 w-12 border-0 bg-transparent text-center font-numeric tabular-nums focus:outline-none focus:ring-0"
        required
    >
    <button type="button" class="h-12 w-11 text-lg text-ink-muted transition hover:text-ink" @click="qty = qty + 1" aria-label="{{ __('Increase') }}">+</button>
</div>
