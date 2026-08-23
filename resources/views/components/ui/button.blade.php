@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'submit',
    'href' => null,
    'disabled' => false,
])

@php
    $variants = [
        'primary' => 'bg-ink-deep text-ink-inverse hover:bg-brand disabled:bg-ink-deep/40',
        'secondary' => 'bg-transparent text-ink border border-ink/15 hover:border-ink/40 hover:bg-canvas disabled:text-ink-muted',
        'ghost' => 'bg-transparent text-ink hover:bg-canvas disabled:text-ink-muted',
        'accent' => 'bg-accent text-ink-inverse hover:bg-accent/90 disabled:bg-accent/50',
        'danger' => 'bg-danger text-ink-inverse hover:bg-danger/90 disabled:bg-danger/50',
        'light' => 'bg-ink-inverse text-ink-deep hover:bg-canvas',
    ];

    $sizes = [
        'sm' => 'h-9 px-3.5 text-caption',
        'md' => 'h-11 px-5 text-label',
        'lg' => 'h-12 px-6 text-label',
    ];

    $classes = trim(
        'inline-flex items-center justify-center gap-2 rounded-sm font-medium tracking-tight transition duration-200 ease-brand active:translate-y-px disabled:cursor-not-allowed '
        .($variants[$variant] ?? $variants['primary']).' '
        .($sizes[$size] ?? $sizes['md'])
    );
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }} @disabled($disabled)>
        {{ $slot }}
    </button>
@endif
