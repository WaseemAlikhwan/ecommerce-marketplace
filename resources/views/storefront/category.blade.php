@php
    $action = $category['url'];
    $breadcrumbs = array_merge(
        [['label' => __('Shop'), 'href' => route('storefront.search')]],
        $category['breadcrumbs'],
    );
    $canonicalQuery = $catalog['query'];
    if ($products->currentPage() > 1) {
        $canonicalQuery['page'] = $products->currentPage();
    }
    $canonical = $action.($canonicalQuery === [] ? '' : '?'.\Illuminate\Support\Arr::query($canonicalQuery));
    $robots = $canonicalQuery === [] ? 'index,follow' : 'noindex,follow';
@endphp

<x-storefront-layout
    :title="$category['name']"
    :description="$category['description'] ?? __('Browse products in :category.', ['category' => $category['name']])"
    :canonical="$canonical"
    :robots="$robots"
    :og-title="$category['name']"
    :og-description="$category['description'] ?? __('Browse products in :category.', ['category' => $category['name']])"
    og-type="website"
    :og-url="$canonical"
    :nav-categories="$navCategories"
    :search-query="$catalog['criteria']['q']"
>
    <div class="border-b border-line bg-surface">
        <div class="ds-container grid gap-8 py-12 md:grid-cols-2 md:items-center">
            <div>
                <x-ui.breadcrumb :items="$breadcrumbs" />
                <h1 class="mt-4 font-display text-display">{{ $category['name'] }}</h1>
                @if ($category['description'])
                    <p class="mt-3 max-w-md text-ink-muted" dir="auto">{{ $category['description'] }}</p>
                @endif
                <p class="mt-4 text-sm text-ink-muted">{{ __(':count products', ['count' => $products->total()]) }}</p>
            </div>
            <div class="relative hidden min-h-52 overflow-hidden bg-ink-deep md:block" aria-hidden="true">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_25%,rgba(196,132,29,0.42),transparent_32%)]"></div>
                <div class="absolute inset-0 ds-pattern opacity-20"></div>
                <span class="absolute -end-4 -top-12 font-display text-[13rem] leading-none text-white/10">{{ mb_substr($category['name'], 0, 1) }}</span>
            </div>
        </div>
    </div>

    @if ($category['children'] !== [])
        <section class="ds-container py-10">
            <h2 class="font-display text-heading-2">{{ __('Explore subcategories') }}</h2>
            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($category['children'] as $child)
                    <x-commerce.category-tile :category="$child" />
                @endforeach
            </div>
        </section>
    @endif

    <div class="ds-container py-12">
        <div class="grid gap-6 lg:grid-cols-12 lg:items-start">
            <x-commerce.catalog-filters
                :action="$action"
                :catalog="$catalog"
                :filters="$filters"
                :clear-url="$action"
                :omit="['category']"
            />

            <section class="min-w-0 lg:col-span-9" aria-label="{{ __('Products') }}">
                @include('storefront.partials.catalog-feedback', [
                    'action' => $action,
                    'clearUrl' => $action,
                    'catalog' => $catalog,
                    'filters' => $filters,
                    'omit' => ['category'],
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
                            <x-ui.empty-state :title="__('No products in this category')" :action="__('Clear all')" :href="$action">
                                {{ __('Try fewer filters or explore another category.') }}
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
