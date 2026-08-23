@props(['title', 'action' => null, 'href' => null])

<div {{ $attributes->class('flex flex-col items-center px-6 py-12 text-center') }}>
    <span class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-lg bg-canvas text-ink-muted">
        {{ $icon ?? '' }}
        @unless (isset($icon))
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <rect x="4" y="6" width="16" height="12" rx="2" stroke="currentColor" stroke-width="1.6"/>
                <path d="M8 10h8M8 14h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
        @endunless
    </span>
    <h3 class="text-heading-3 text-ink">{{ $title }}</h3>
    <p class="mt-2 max-w-md text-sm text-ink-muted">{{ $slot }}</p>
    @if ($action && $href)
        <div class="mt-5">
            <x-ui.button :href="$href" variant="secondary" type="button">{{ $action }}</x-ui.button>
        </div>
    @endif
</div>
