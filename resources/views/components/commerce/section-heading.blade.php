@props(['kicker' => null, 'title', 'aside' => null])

<div {{ $attributes->class('mb-10 flex flex-col gap-4 md:mb-12 md:flex-row md:items-end md:justify-between') }}>
    <div class="max-w-2xl">
        @if ($kicker)
            <p class="ds-section-kicker">{{ $kicker }}</p>
        @endif
        <h2 class="ds-section-title mt-2">{{ $title }}</h2>
        @isset($description)
            <p class="mt-3 max-w-lg text-sm leading-relaxed text-ink-muted">{{ $description }}</p>
        @endisset
    </div>
    @if ($aside || isset($actions))
        <div class="shrink-0 text-caption text-ink-muted">
            {{ $aside }}
            {{ $actions ?? '' }}
        </div>
    @endif
</div>
