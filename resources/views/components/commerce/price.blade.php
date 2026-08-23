@props(['label', 'compareLabel' => null, 'size' => 'md'])

<div {{ $attributes->class('flex flex-wrap items-baseline gap-x-2 gap-y-1') }}>
    <span @class(['ds-price', 'text-[1.65rem] leading-none' => $size === 'lg'])>{{ $label }}</span>
    @if ($compareLabel)
        <span class="text-caption text-ink-muted line-through">{{ $compareLabel }}</span>
    @endif
</div>
