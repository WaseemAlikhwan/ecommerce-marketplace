@props(['value'])

<label {{ $attributes->merge(['class' => 'ds-label mb-1.5 block']) }}>
    {{ $value ?? $slot }}
</label>
