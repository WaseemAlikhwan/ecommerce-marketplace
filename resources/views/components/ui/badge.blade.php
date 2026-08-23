@props(['tone' => 'neutral'])

@php
    $tones = [
        'neutral' => 'bg-canvas text-ink-muted border-line',
        'brand' => 'bg-brand-soft text-brand border-brand/20',
        'accent' => 'bg-accent-soft text-accent border-accent/20',
        'success' => 'bg-success-soft text-success border-success/20',
        'warning' => 'bg-warning-soft text-warning border-warning/20',
        'danger' => 'bg-danger-soft text-danger border-danger/20',
    ];
@endphp

<span {{ $attributes->class('inline-flex items-center rounded-pill border px-2.5 py-0.5 text-caption font-medium '.($tones[$tone] ?? $tones['neutral'])) }}>
    {{ $slot }}
</span>
