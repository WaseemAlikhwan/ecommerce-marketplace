@props(['images' => []])

@php
    $primaryIndex = 0;
    foreach ($images as $index => $image) {
        if ($image['is_primary']) {
            $primaryIndex = $index;
            break;
        }
    }
    $hero = $images[$primaryIndex] ?? null;
    $mobileImages = $hero === null
        ? []
        : array_values(array_merge(
            [$hero],
            array_filter($images, static fn (array $image): bool => $image['id'] !== $hero['id']),
        ));
@endphp

<div
    {{ $attributes->class('min-w-0') }}
    x-data="{ active: {{ $primaryIndex }}, broken: false, images: {{ Js::from($images) }} }"
>
    <div class="relative hidden lg:block">
        <div class="ds-gallery-stage relative aspect-[4/5] bg-gradient-to-br from-canvas to-line/60">
            <span class="absolute inset-0 flex flex-col items-center justify-center gap-3 text-ink-muted" @if ($hero) aria-hidden="true" @endif>
                <svg class="h-16 w-16" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M8 35 18 24l7 7 5-6 10 10M16 18h.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><rect x="7" y="9" width="34" height="30" rx="2" stroke="currentColor" stroke-width="1.5"/></svg>
                <span class="text-sm">{{ __('Image unavailable') }}</span>
            </span>
            @if ($hero)
                <a href="{{ $hero['url'] }}" :href="images[active].url" class="relative block h-full w-full">
                    <img
                        x-ref="hero"
                        src="{{ $hero['url'] }}"
                        alt="{{ $hero['alt'] }}"
                        @if ($hero['width']) width="{{ $hero['width'] }}" @endif
                        @if ($hero['height']) height="{{ $hero['height'] }}" @endif
                        :src="images[active].url"
                        :alt="images[active].alt"
                        :width="images[active].width || null"
                        :height="images[active].height || null"
                        x-on:error="broken = true; $el.hidden = true; $el.onerror = null"
                        x-on:load="broken = false; $el.hidden = false"
                        onerror="this.onerror=null;this.hidden=true;this.closest('a')?.previousElementSibling?.removeAttribute('aria-hidden')"
                        onload="this.closest('a')?.previousElementSibling?.setAttribute('aria-hidden','true')"
                        loading="eager"
                        fetchpriority="high"
                        decoding="async"
                        class="relative h-full w-full object-cover"
                    >
                </a>
            @endif
        </div>
        @if (count($images) > 1)
            <div class="mt-3 grid grid-cols-4 gap-2">
                @foreach ($images as $index => $image)
                    <a
                        href="{{ $image['url'] }}"
                        class="relative overflow-hidden border bg-canvas transition"
                        :class="active === {{ $index }} ? 'border-ink-deep' : 'border-transparent hover:border-line'"
                        @click.prevent="active = {{ $index }}; broken = false; $refs.hero.hidden = false"
                        aria-current="{{ $index === $primaryIndex ? 'true' : 'false' }}"
                        x-effect="$el.setAttribute('aria-current', active === {{ $index }} ? 'true' : 'false')"
                        aria-label="{{ __('Image :n', ['n' => $index + 1]) }}"
                    >
                        <span class="absolute inset-0 flex items-center justify-center text-caption text-ink-muted">{{ __('Image unavailable') }}</span>
                        <img
                            src="{{ $image['url'] }}"
                            alt=""
                            @if ($image['width']) width="{{ $image['width'] }}" @endif
                            @if ($image['height']) height="{{ $image['height'] }}" @endif
                            loading="lazy"
                            decoding="async"
                            class="relative aspect-square w-full object-cover"
                            onerror="this.onerror=null;this.hidden=true"
                        >
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <div class="-mx-4 flex snap-x snap-mandatory gap-0 overflow-x-auto pb-1 lg:hidden" role="list">
        @forelse ($mobileImages as $image)
            <figure class="relative min-w-full snap-center bg-gradient-to-br from-canvas to-line/60 px-4" role="listitem">
                <span class="absolute inset-4 flex items-center justify-center bg-canvas text-sm text-ink-muted" aria-hidden="true">{{ __('Image unavailable') }}</span>
                <a href="{{ $image['url'] }}" class="relative block">
                    <img
                        src="{{ $image['url'] }}"
                        alt="{{ $image['alt'] }}"
                        @if ($image['width']) width="{{ $image['width'] }}" @endif
                        @if ($image['height']) height="{{ $image['height'] }}" @endif
                        loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                        @if ($loop->first) fetchpriority="high" @endif
                        decoding="async"
                        class="relative aspect-[4/5] w-full object-cover"
                        onerror="this.onerror=null;this.hidden=true;this.closest('a')?.previousElementSibling?.removeAttribute('aria-hidden')"
                    >
                </a>
            </figure>
        @empty
            <div class="flex aspect-[4/5] min-w-full items-center justify-center bg-canvas text-ink-muted">
                {{ __('Image unavailable') }}
            </div>
        @endforelse
    </div>
</div>
