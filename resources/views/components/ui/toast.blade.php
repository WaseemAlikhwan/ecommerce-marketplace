@props(['tone' => 'info', 'title' => null])

@php
    $tones = [
        'info' => 'border-brand/20 bg-elevated',
        'success' => 'border-success/25 bg-success-soft',
        'warning' => 'border-warning/25 bg-warning-soft',
        'danger' => 'border-danger/25 bg-danger-soft',
    ];
@endphp

<div {{ $attributes->class('pointer-events-auto w-full max-w-sm rounded-md border px-4 py-3 shadow-md '.($tones[$tone] ?? $tones['info'])) }} role="status">
    @if ($title)
        <p class="text-label text-ink">{{ $title }}</p>
    @endif
    <p @class(['text-sm text-ink-muted', 'mt-0.5' => (bool) $title])>{{ $slot }}</p>
</div>
