@php($hero = $home['hero_product'])
<x-storefront-layout
    :title="__('Sham Market')"
    :description="__('Discover products from independent stores in one marketplace.')"
    :canonical="route('home')"
    robots="index,follow"
    :og-title="__('Sham Market')"
    :og-description="__('Discover products from independent stores in one marketplace.')"
    og-type="website"
    :og-url="route('home')"
    :og-image="$hero['image']['url'] ?? null"
    :nav-categories="$navCategories"
>
    <section class="relative overflow-hidden border-b border-line bg-canvas">
        <div class="pointer-events-none absolute inset-0 ds-pattern opacity-35"></div>
        <div class="ds-container relative grid items-center gap-10 py-12 md:gap-14 lg:grid-cols-12 lg:py-24">
            <div class="ds-reveal lg:col-span-6">
                <p class="ds-section-kicker">{{ __('Sham Market') }}</p>
                <h1 class="ds-hero-title mt-5 max-w-xl">{{ __('Discover products from independent stores.') }}</h1>
                <p class="mt-6 max-w-md text-base leading-relaxed text-ink-muted md:text-[1.05rem]">{{ __('Browse the newest available products and visit the sellers behind them.') }}</p>
                <div class="mt-9 flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
                    <x-ui.button :href="route('storefront.search')" variant="primary" size="lg" type="button" class="min-w-[12.5rem]">{{ __('Browse products') }}</x-ui.button>
                    <x-ui.button href="#stores" variant="ghost" size="lg" type="button">{{ __('Meet the stores') }}</x-ui.button>
                </div>
            </div>
            <div class="relative min-h-[20rem] lg:col-span-6 lg:min-h-[31rem]">
                @if ($hero)
                    <a href="{{ $hero['url'] }}" class="ds-arch group absolute inset-x-8 inset-y-0 overflow-hidden bg-ink-deep focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand">
                        @if ($hero['image']['url'])
                            <img
                                src="{{ $hero['image']['url'] }}"
                                alt="{{ $hero['image']['alt'] }}"
                                @if ($hero['image']['width']) width="{{ $hero['image']['width'] }}" @endif
                                @if ($hero['image']['height']) height="{{ $hero['image']['height'] }}" @endif
                                class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.02]"
                                loading="eager"
                                fetchpriority="high"
                                decoding="async"
                                onerror="this.onerror=null;this.hidden=true"
                            >
                        @else
                            <span class="absolute inset-0 bg-[radial-gradient(circle_at_25%_25%,rgba(196,132,29,0.45),transparent_28%),radial-gradient(circle_at_75%_70%,rgba(255,255,255,0.12),transparent_34%)]" aria-hidden="true"></span>
                        @endif
                        <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-ink-deep/90 to-transparent px-6 pb-6 pt-20 text-ink-inverse">
                            <span class="block text-caption opacity-80">{{ __('Newest product') }}</span>
                            <span class="mt-1 block font-display text-heading-2">{{ $hero['name'] }}</span>
                        </span>
                    </a>
                @else
                    <div class="ds-arch absolute inset-x-8 inset-y-0 overflow-hidden bg-gradient-to-br from-brand/20 via-canvas to-accent/20">
                        <span class="absolute inset-0 flex items-center justify-center px-8 text-center font-display text-heading-2 text-ink-muted">{{ __('New products will appear here.') }}</span>
                    </div>
                @endif
            </div>
        </div>
    </section>

    <section class="ds-container py-16 md:py-24">
        <x-commerce.section-heading :kicker="__('Departments')" :title="__('Start where the house starts.')">
            <x-slot:actions>
                <a href="{{ route('storefront.search') }}" class="hidden text-sm text-ink-muted transition hover:text-ink md:inline">{{ __('View all') }}</a>
            </x-slot:actions>
        </x-commerce.section-heading>
        @forelse ($home['categories'] as $category)
            @if ($loop->first)
                <div class="grid gap-3 md:grid-cols-12">
            @endif
                <div class="{{ $loop->first ? 'md:col-span-8' : 'md:col-span-4' }}">
                    <x-commerce.category-tile :category="$category" :featured="$loop->first" />
                </div>
            @if ($loop->last)
                </div>
            @endif
        @empty
            <x-ui.empty-state :title="__('No categories available')">
                {{ __('Active catalog categories will appear here.') }}
            </x-ui.empty-state>
        @endforelse
    </section>

    <section class="border-y border-line bg-surface py-16 md:py-24">
        <div class="ds-container">
            <x-commerce.section-heading :kicker="__('New arrivals')" :title="__('Recently published products')" />
            <div class="grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-4">
                @forelse ($home['products'] as $product)
                    <x-commerce.product-card :product="$product" />
                @empty
                    <div class="col-span-full">
                        <x-ui.empty-state :title="__('No products available')">
                            {{ __('Published products from eligible stores will appear here.') }}
                        </x-ui.empty-state>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section id="stores" class="ds-container py-16 md:py-24">
        <x-commerce.section-heading :kicker="__('Local sellers')" :title="__('Approved stores')" />
        <div class="grid gap-3 lg:grid-cols-2">
            @forelse ($home['stores'] as $store)
                <x-commerce.store-card :store="$store" />
            @empty
                <div class="lg:col-span-2">
                    <x-ui.empty-state :title="__('No stores available')">
                        {{ __('Eligible stores will appear here.') }}
                    </x-ui.empty-state>
                </div>
            @endforelse
        </div>
    </section>
</x-storefront-layout>
