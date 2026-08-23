@props(['product', 'featured' => false])

<article {{ $attributes->class(['group min-w-0', 'lg:flex lg:flex-col' => $featured]) }}>
    <div class="relative overflow-hidden bg-canvas">
        <a href="{{ $product['url'] }}" class="ds-product-media focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand {{ $featured ? 'aspect-[4/5] lg:aspect-[5/4]' : 'aspect-[4/5]' }}">
            <span class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-gradient-to-br from-canvas to-line/60 text-ink-muted" @if ($product['image']['url']) aria-hidden="true" @endif>
                <svg class="h-12 w-12" viewBox="0 0 48 48" fill="none"><path d="M8 35 18 24l7 7 5-6 10 10M16 18h.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><rect x="7" y="9" width="34" height="30" rx="2" stroke="currentColor" stroke-width="1.5"/></svg>
                <span class="text-caption">{{ __('Image unavailable') }}</span>
            </span>
            @if ($product['image']['url'])
                <img
                    src="{{ $product['image']['url'] }}"
                    alt="{{ $product['image']['alt'] }}"
                    @if ($product['image']['width']) width="{{ $product['image']['width'] }}" @endif
                    @if ($product['image']['height']) height="{{ $product['image']['height'] }}" @endif
                    loading="lazy"
                    decoding="async"
                    class="relative h-full w-full object-cover bg-line/40"
                    onerror="this.onerror=null;this.hidden=true;this.previousElementSibling?.removeAttribute('aria-hidden')"
                >
            @endif
        </a>
        @unless ($product['in_stock'])
            <span class="absolute start-3 top-3 bg-ink-deep px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-ink-inverse">{{ __('Out of stock') }}</span>
        @endunless
    </div>
    <div class="pt-3.5">
        <h3 @class(['leading-snug', 'font-display text-heading-3' => $featured, 'text-[0.95rem] font-medium' => ! $featured])>
            <a href="{{ $product['url'] }}" class="text-ink transition hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">{{ $product['name'] }}</a>
        </h3>
        <div class="mt-2">
            <x-commerce.price :label="$product['price_label']" :compare-label="$product['compare_at_label']" />
        </div>
        <div class="mt-2">
            <a href="{{ $product['store']['url'] }}" class="text-caption text-ink-muted transition hover:text-ink">{{ $product['store']['name'] }}</a>
        </div>
        <div class="mt-3">
            @if (($product['is_simple'] ?? false) && ($product['default_variant_id'] ?? null) && ($product['in_stock'] ?? false))
                <form method="post" action="{{ route('cart.items.store') }}" class="flex items-center gap-2">
                    @csrf
                    <input type="hidden" name="variant_id" value="{{ $product['default_variant_id'] }}">
                    <input type="hidden" name="quantity" value="1">
                    <x-ui.button variant="secondary" size="sm" class="w-full">{{ __('Add to cart') }}</x-ui.button>
                </form>
            @elseif (! ($product['is_simple'] ?? true))
                <x-ui.button :href="$product['url']" variant="ghost" size="sm" type="button" class="w-full">{{ __('Choose options') }}</x-ui.button>
            @endif
        </div>
    </div>
</article>
