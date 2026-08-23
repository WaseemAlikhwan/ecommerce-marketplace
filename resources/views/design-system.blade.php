@php
    $designProduct = [
        'url' => route('storefront.search'),
        'name' => __('Sample product 1'),
        'image' => ['url' => null, 'alt' => __('Sample product 1'), 'width' => null, 'height' => null],
        'price_label' => '185,000 SYP',
        'compare_at_label' => null,
        'in_stock' => true,
        'is_simple' => true,
        'default_variant_id' => null,
        'store' => ['name' => __('Vendor store name'), 'url' => route('storefront.search')],
    ];
@endphp

<x-storefront-layout :title="__('Design system')" :nav-categories="[]">
    <div class="ds-container py-12">
        <p class="ds-section-kicker">{{ __('Internal') }}</p>
        <h1 class="mt-2 font-display text-display">{{ __('Design system') }}</h1>
        <p class="mt-3 max-w-2xl text-ink-muted">{{ __('Reusable tokens and components for Sham Market. This page is a visual reference, not a customer destination.') }}</p>

        <div class="mt-14 space-y-16">
            <section>
                <h2 class="ds-section-title">{{ __('Color tokens') }}</h2>
                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach (['canvas' => 'bg-canvas', 'surface' => 'bg-surface', 'ink deep' => 'bg-ink-deep', 'primary' => 'bg-brand', 'accent' => 'bg-accent', 'text' => 'bg-ink', 'muted' => 'bg-ink-muted', 'border' => 'bg-line'] as $label => $class)
                        <div class="overflow-hidden border border-line">
                            <div class="h-16 {{ $class }}"></div>
                            <p class="px-3 py-2 text-caption">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section>
                <h2 class="ds-section-title">{{ __('Typography') }}</h2>
                <div class="mt-6 space-y-3 border border-line bg-surface p-8">
                    <p class="font-display text-display">{{ __('Goods with a place of origin.') }}</p>
                    <p class="text-heading-1">{{ __('Heading one') }}</p>
                    <p class="text-body text-ink-muted">{{ __('Body text is sized for comfortable Arabic and English reading, with generous line height.') }}</p>
                    <p class="ds-price">185,000 {{ __('SYP') }}</p>
                </div>
            </section>

            <section>
                <h2 class="ds-section-title">{{ __('Buttons') }}</h2>
                <div class="mt-6 flex flex-wrap gap-3">
                    <x-ui.button type="button">{{ __('Primary') }}</x-ui.button>
                    <x-ui.button type="button" variant="secondary">{{ __('Secondary') }}</x-ui.button>
                    <x-ui.button type="button" variant="ghost">{{ __('Ghost') }}</x-ui.button>
                    <x-ui.button type="button" variant="accent">{{ __('Accent') }}</x-ui.button>
                    <x-ui.button type="button" disabled>{{ __('Disabled') }}</x-ui.button>
                </div>
            </section>

            <section>
                <h2 class="ds-section-title">{{ __('Commerce') }}</h2>
                <div class="mt-6 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    <x-commerce.product-card :product="$designProduct" />
                </div>
            </section>
        </div>
    </div>
</x-storefront-layout>
