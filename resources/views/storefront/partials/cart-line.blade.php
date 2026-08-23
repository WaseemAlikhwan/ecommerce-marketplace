@php
    /** @var \App\Cart\CartViewLine $line */
    $isUnavailable = $line->status === \App\Cart\CartViewLine::STATUS_UNAVAILABLE;
    $isAdjusted = $line->status === \App\Cart\CartViewLine::STATUS_ADJUSTED;
    $detailUrl = $line->productSlug !== ''
        ? route('storefront.product', $line->productSlug)
        : null;
@endphp

<article @class([
    'grid gap-4 border border-line bg-surface p-4 sm:grid-cols-[7.5rem_minmax(0,1fr)] sm:gap-6 sm:p-5',
    'opacity-80' => $isUnavailable,
])>
    <div class="aspect-[4/5] overflow-hidden bg-canvas">
        @if ($line->imageUrl)
            <img
                src="{{ $line->imageUrl }}"
                alt="{{ $line->imageAlt ?? $line->productName }}"
                @if ($line->imageWidth) width="{{ $line->imageWidth }}" @endif
                @if ($line->imageHeight) height="{{ $line->imageHeight }}" @endif
                class="h-full w-full object-cover"
                loading="lazy"
                decoding="async"
            >
        @else
            <div class="flex h-full items-center justify-center text-caption text-ink-muted">{{ __('No image') }}</div>
        @endif
    </div>

    <div class="min-w-0">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                @if ($line->storeName !== '')
                    <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ $line->storeName }}</p>
                @endif
                <h2 class="mt-1 font-display text-heading-3">
                    @if ($detailUrl)
                        <a href="{{ $detailUrl }}" class="transition hover:text-brand">{{ $line->productName !== '' ? $line->productName : __('Unavailable item') }}</a>
                    @else
                        {{ $line->productName !== '' ? $line->productName : __('Unavailable item') }}
                    @endif
                </h2>

                @if ($line->selection !== [])
                    <ul class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-sm text-ink-muted">
                        @foreach ($line->selection as $option)
                            <li>
                                <span class="text-ink">{{ $option['attribute_name'] }}</span>:
                                {{ $option['value_name'] }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="text-end">
                @if ($line->unitPrice)
                    <p class="ds-price text-base">{{ $formatMoney($line->unitPrice) }}</p>
                @endif
                @if ($line->lineTotal)
                    <p class="mt-1 text-sm text-ink-muted">{{ __('Line total') }}: {{ $formatMoney($line->lineTotal) }}</p>
                @endif
            </div>
        </div>

        @if ($isUnavailable)
            <p class="mt-3 border-s-2 border-danger ps-3 text-sm text-danger">{{ $unavailableLabel($line->unavailableReason) }}</p>
        @elseif ($isAdjusted)
            <p class="mt-3 border-s-2 border-warning ps-3 text-sm text-warning">
                {{ __('Quantity adjusted from :from to :to to match available stock.', [
                    'from' => $line->requestedQuantity,
                    'to' => $line->effectiveQuantity,
                ]) }}
            </p>
        @endif

        <div class="mt-4 flex flex-wrap items-center gap-3">
            @unless ($isUnavailable)
                <form method="post" action="{{ route('cart.items.update', $line->variantId) }}" class="flex flex-wrap items-center gap-2">
                    @csrf
                    @method('PATCH')
                    <label class="sr-only" for="qty-{{ $line->variantId }}">{{ __('Quantity') }}</label>
                    <x-commerce.qty
                        id="qty-{{ $line->variantId }}"
                        name="quantity"
                        :value="$line->effectiveQuantity"
                        :min="0"
                    />
                    <x-ui.button variant="secondary" size="sm">{{ __('Update') }}</x-ui.button>
                </form>
            @endunless

            <form method="post" action="{{ route('cart.items.destroy', $line->variantId) }}">
                @csrf
                @method('DELETE')
                <x-ui.button variant="ghost" size="sm">{{ __('Remove') }}</x-ui.button>
            </form>
        </div>
    </div>
</article>
