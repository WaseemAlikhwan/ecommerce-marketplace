@props(['store'])

<aside {{ $attributes->class('flex items-center gap-4 border border-line bg-surface p-4') }}>
    <span class="relative inline-flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden bg-brand text-sm font-semibold text-ink-inverse" aria-hidden="true">
        <span>{{ $store['initials'] }}</span>
        @if ($store['logo_url'])
            <img src="{{ $store['logo_url'] }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy" decoding="async" onerror="this.onerror=null;this.remove()">
        @endif
    </span>
    <div class="min-w-0 flex-1">
        <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Seller') }}</p>
        <p class="mt-0.5 truncate font-display text-label">
            <a href="{{ $store['url'] }}" class="hover:text-brand">{{ $store['name'] }}</a>
        </p>
        @if ($store['description'])
            <p class="mt-1 line-clamp-1 text-caption text-ink-muted" dir="auto">{{ $store['description'] }}</p>
        @endif
    </div>
    <x-ui.button :href="$store['url']" variant="secondary" size="sm" type="button">{{ __('Visit store') }}</x-ui.button>
</aside>
