@props(['store'])

<article {{ $attributes->class('flex flex-col gap-4 border border-line bg-surface p-5 transition duration-200 hover:border-ink/25 sm:flex-row sm:items-center') }}>
    <div class="flex min-w-0 flex-1 items-center gap-4">
        <span class="relative inline-flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden bg-brand text-sm font-semibold tracking-wide text-ink-inverse" aria-hidden="true">
            <span>{{ $store['initials'] }}</span>
            @if ($store['logo_url'])
                <img src="{{ $store['logo_url'] }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy" decoding="async" onerror="this.onerror=null;this.remove()">
            @endif
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Store') }}</p>
            <h3 class="mt-1 truncate font-display text-heading-3">
                <a href="{{ $store['url'] }}" class="hover:text-brand">{{ $store['name'] }}</a>
            </h3>
            <p class="mt-1.5 text-caption text-ink-muted">{{ __(':count products', ['count' => $store['visible_product_count']]) }}</p>
        </div>
    </div>
    <x-ui.button :href="$store['url']" variant="secondary" size="sm" type="button" class="w-full shrink-0 sm:w-auto">{{ __('Visit store') }}</x-ui.button>
</article>
