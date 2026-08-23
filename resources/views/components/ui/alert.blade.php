@props(['tone' => 'info', 'title' => null])

@php
    $tones = [
        'info' => 'border-brand/20 bg-brand-soft text-ink',
        'success' => 'border-success/20 bg-success-soft text-ink',
        'warning' => 'border-warning/20 bg-warning-soft text-ink',
        'danger' => 'border-danger/20 bg-danger-soft text-ink',
    ];
@endphp

<div {{ $attributes->class('rounded-md border px-4 py-3 text-sm '.($tones[$tone] ?? $tones['info'])) }} role="status">
    @if ($title)
        <p class="font-medium">{{ $title }}</p>
    @endif
    <div @class(['mt-1' => (bool) $title])>{{ $slot }}</div>
</div>
