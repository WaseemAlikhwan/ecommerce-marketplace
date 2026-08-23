@php
    $action = route('storefront.search');
    $canonicalQuery = $catalog['query'];
    if ($products->currentPage() > 1) {
        $canonicalQuery['page'] = $products->currentPage();
    }
    $canonical = $action.($canonicalQuery === [] ? '' : '?'.\Illuminate\Support\Arr::query($canonicalQuery));
    $seoTitle = $catalog['criteria']['q']
        ? __('Search results for :query', ['query' => $catalog['criteria']['q']])
        : __('Browse products');
@endphp

<x-storefront-layout
    :title="$seoTitle"
    :description="__('Search and filter products from eligible marketplace stores.')"
    :canonical="$canonical"
    robots="noindex,follow"
    :og-title="$seoTitle"
    :og-description="__('Search and filter products from eligible marketplace stores.')"
    og-type="website"
    :og-url="$canonical"
    :nav-categories="$navCategories"
    :search-query="$catalog['criteria']['q']"
>
    <div class="ds-container py-10 md:py-14">
        <p class="ds-section-kicker">{{ __('Search') }}</p>
        <h1 class="mt-2 font-display text-heading-1">
            {{ $catalog['criteria']['q'] ? __('Results for “:q”', ['q' => $catalog['criteria']['q']]) : __('All products') }}
        </h1>
        <p class="mt-2 text-sm text-ink-muted">{{ __('Use filters to narrow products from eligible stores.') }}</p>

        <div class="mt-8 grid gap-6 lg:grid-cols-12 lg:items-start">
            <x-commerce.catalog-filters
                :action="$action"
                :catalog="$catalog"
                :filters="$filters"
                :clear-url="$action"
            />

            <section class="min-w-0 lg:col-span-9" aria-label="{{ __('Products') }}">
                @include('storefront.partials.catalog-feedback', [
                    'action' => $action,
                    'clearUrl' => $action,
                    'catalog' => $catalog,
                    'filters' => $filters,
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
                            <x-ui.empty-state :title="__('No matching products')" :action="__('Clear all')" :href="$action">
                                {{ __('Try fewer filters or a different search phrase.') }}
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
