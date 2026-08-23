@props(['elevated' => false, 'padding' => true])

<div {{ $attributes->class(($elevated ? 'ds-card-elevated' : 'ds-card').($padding ? ' p-5 sm:p-6' : '')) }}>
    {{ $slot }}
</div>
