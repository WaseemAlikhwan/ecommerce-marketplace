@php
    $action = $store['url'];
    $canonicalQuery = $catalog['query'];
    if ($products->currentPage() > 1) {
        $canonicalQuery['page'] = $products->currentPage();
    }
    $canonical = $action.($canonicalQuery === [] ? '' : '?'.\Illuminate\Support\Arr::query($canonicalQuery));
    $robots = $canonicalQuery === [] ? 'index,follow' : 'noindex,follow';
@endphp

<x-storefront-layout
    :title="$store['name']"
    :description="$store['description'] ?? __('Browse products from :store.', ['store' => $store['name']])"
    :canonical="$canonical"
    :robots="$robots"
    :og-title="$store['name']"
    :og-description="$store['description'] ?? __('Browse products from :store.', ['store' => $store['name']])"
    og-type="website"
    :og-url="$canonical"
    :nav-categories="$navCategories"
    :search-query="$catalog['criteria']['q']"
>
    <div class="relative overflow-hidden border-b border-line bg-ink-deep text-ink-inverse">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(196,132,29,0.38),transparent_34%)]"></div>
        <div class="absolute inset-0 ds-pattern opacity-15"></div>
        @if ($store['banner_url'])
            <img src="{{ $store['banner_url'] }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-35" decoding="async" onerror="this.onerror=null;this.remove()">
        @endif
        <div class="ds-container relative flex flex-col gap-6 py-14 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex items-center gap-4">
                <span class="relative inline-flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden bg-brand text-lg font-semibold" aria-hidden="true">
                    <span>{{ $store['initials'] }}</span>
                    @if ($store['logo_url'])
                        <img src="{{ $store['logo_url'] }}" alt="" class="absolute inset-0 h-full w-full object-cover" decoding="async" onerror="this.onerror=null;this.remove()">
                    @endif
                </span>
                <div>
                    <p class="text-[11px] uppercase tracking-[0.16em] text-ink-inverse/55">{{ __('Store') }}</p>
                    <h1 class="mt-1 font-display text-heading-1">{{ $store['name'] }}</h1>
                    <p class="mt-2 text-caption text-ink-inverse/70">{{ __(':count products', ['count' => $store['visible_product_count']]) }}</p>
                </div>
            </div>
            <x-ui.button href="#collection" variant="light" type="button">{{ __('View collection') }}</x-ui.button>
        </div>
    </div>

    @if ($store['description'])
        <div class="border-b border-line bg-surface">
            <div class="ds-container py-7">
                <p class="max-w-3xl text-sm leading-relaxed text-ink-muted" dir="auto">{{ $store['description'] }}</p>
            </div>
        </div>
    @endif

    <div id="collection" class="ds-container py-12">
        <div class="grid gap-6 lg:grid-cols-12 lg:items-start">
            <x-commerce.catalog-filters
                :action="$action"
                :catalog="$catalog"
                :filters="$filters"
                :clear-url="$action"
                :omit="['store']"
            />

            <section class="min-w-0 lg:col-span-9" aria-label="{{ __('Products') }}">
                @include('storefront.partials.catalog-feedback', [
                    'action' => $action,
                    'clearUrl' => $action,
                    'catalog' => $catalog,
                    'filters' => $filters,
                    'omit' => ['store'],
                ])

                <div class="mb-6 flex items-center justify-between gap-4 border-b border-line pb-3">
                    <p class="text-sm text-ink-muted">{{ __(':count products', ['count' => $products->total()]) }}</p>
                    <p class="text-caption text-ink-muted">{{ __('Page :current of :last', ['current' => $products->currentPage(), 'last' => max(1, $products->lastPage())]) }}</p>
                </div>

                <div class="grid gap-x-5 gap-y-10 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse ($products as $product)
                        <x-commerce.product-card :product="$product" />
                    @empty
                        <div class="col-span-full border border-line bg-surface">
                            <x-ui.empty-state :title="__('No products from this store')" :action="__('Clear all')" :href="$action">
                                {{ __('Try fewer filters or browse all products.') }}
                            </x-ui.empty-state>
                        </div>
                    @endforelse
                </div>

                @if ($products->hasPages())
                    <div class="mt-12">
                        <x-ui.pagination :paginator="$products->appends($catalog['query'])" />
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-storefront-layout>
