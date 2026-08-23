@props(['title', 'description' => null])

<div {{ $attributes->class('mb-6 flex flex-col gap-4 sm:mb-8 sm:flex-row sm:items-end sm:justify-between') }}>
    <div>
        {{ $breadcrumb ?? '' }}
        <h1 class="ds-page-title">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 max-w-2xl text-sm text-ink-muted">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
