@props(['title', 'subtitle' => null])

<div {{ $attributes->class('mb-8') }}>
    <h1 class="font-display text-heading-1 tracking-tight text-ink">{{ $title }}</h1>
    @if ($subtitle)
        <p class="mt-2 text-sm leading-relaxed text-ink-muted">{{ $subtitle }}</p>
    @endif
</div>
