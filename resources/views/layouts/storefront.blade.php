@props([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'robots' => 'index,follow',
    'ogTitle' => null,
    'ogDescription' => null,
    'ogType' => 'website',
    'ogUrl' => null,
    'ogImage' => null,
    'navCategories' => [],
    'searchQuery' => null,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Locale::direction(app()->getLocale()) }}">
<head>
    @include('layouts.partials.head')
</head>
<body class="min-h-screen bg-canvas" x-data="storefrontDialog()">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-50 focus:bg-elevated focus:px-3 focus:py-2">{{ __('Skip to content') }}</a>

    <header class="sticky top-0 z-40" data-storefront-background>
        <div class="bg-ink-deep text-ink-inverse">
            <div class="ds-container flex h-8 items-center justify-between gap-4 text-[11px] tracking-wide">
                <a href="{{ route('storefront.search') }}" class="truncate text-ink-inverse/70 transition hover:text-ink-inverse">{{ __('Browse products from approved local stores.') }}</a>
                <div class="flex shrink-0 items-center gap-4">
                    @include('partials.locale-switcher', ['compact' => true, 'inverse' => true])
                    <a href="{{ route('register') }}" class="hidden text-ink-inverse/75 transition hover:text-ink-inverse sm:inline">{{ __('Sell on Sham') }}</a>
                </div>
            </div>
        </div>

        <div class="border-b border-line bg-surface/95 backdrop-blur">
            <div class="ds-container hidden h-[4.5rem] items-center gap-6 lg:flex">
                <x-brand.logo class="shrink-0" />

                <nav class="hidden shrink-0 items-center gap-5 xl:flex" aria-label="{{ __('Categories') }}">
                    @foreach (array_slice($navCategories, 0, \App\Services\Storefront\StorefrontNavigationService::DESKTOP_CATEGORY_LIMIT) as $category)
                        <a href="{{ $category['url'] }}" class="whitespace-nowrap text-[0.8rem] text-ink-muted transition hover:text-ink">{{ $category['name'] }}</a>
                    @endforeach
                </nav>

                <form class="min-w-0 flex-1" action="{{ route('storefront.search') }}" method="get" role="search">
                    <label class="sr-only" for="storefront-search">{{ __('Search products') }}</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 start-4 flex items-center text-ink-muted">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.6"/><path d="M16 16l4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        </span>
                        <input id="storefront-search" type="search" name="q" value="{{ $searchQuery }}" class="ds-search" placeholder="{{ __('Search products') }}">
                        <button type="submit" class="absolute inset-y-0 end-1 my-1 inline-flex items-center px-3 text-caption text-ink-muted transition hover:text-ink">{{ __('Search') }}</button>
                    </div>
                </form>

                <div class="flex shrink-0 items-center gap-1">
                    <a href="{{ route('cart.show') }}" class="ds-icon-btn" aria-label="{{ __('Cart') }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6.5 8h11l-.8 9.2a2 2 0 0 1-2 1.8H9.3a2 2 0 0 1-2-1.8L6.5 8Z" stroke="currentColor" stroke-width="1.6"/><path d="M9 8V6.5A3 3 0 0 1 12 3.5v0a3 3 0 0 1 3 3V8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </a>
                    @auth
                        <x-ui.button :href="route('dashboard')" variant="ghost" size="sm" type="button">{{ __('Account') }}</x-ui.button>
                    @else
                        <x-ui.button :href="route('login')" variant="ghost" size="sm" type="button">{{ __('Log in') }}</x-ui.button>
                        <x-ui.button :href="route('register')" variant="primary" size="sm" type="button">{{ __('Join') }}</x-ui.button>
                    @endauth
                </div>
            </div>

            <div class="lg:hidden">
                <div class="ds-container flex h-14 items-center gap-2">
                    <button
                        type="button"
                        class="ds-icon-btn"
                        x-ref="trigger"
                        @click="showDialog()"
                        :aria-expanded="open.toString()"
                        aria-controls="storefront-mobile-navigation"
                        aria-label="{{ __('Open menu') }}"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    </button>
                    <x-brand.logo compact class="mx-auto" />
                    <a href="{{ route('cart.show') }}" class="ds-icon-btn" aria-label="{{ __('Cart') }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6.5 8h11l-.8 9.2a2 2 0 0 1-2 1.8H9.3a2 2 0 0 1-2-1.8L6.5 8Z" stroke="currentColor" stroke-width="1.6"/><path d="M9 8V6.5A3 3 0 0 1 12 3.5v0a3 3 0 0 1 3 3V8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </a>
                    <a href="{{ route(auth()->check() ? 'dashboard' : 'login') }}" class="ds-icon-btn" aria-label="{{ __('Account') }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.6"/><path d="M5 20c.8-4 3.1-6 7-6s6.2 2 7 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </a>
                </div>
                <form class="border-t border-line px-4 py-2.5" action="{{ route('storefront.search') }}" method="get" role="search">
                    <label class="sr-only" for="storefront-search-mobile">{{ __('Search products') }}</label>
                    <div class="relative">
                        <input id="storefront-search-mobile" type="search" name="q" value="{{ $searchQuery }}" class="ds-search h-11 pe-24 ps-4" placeholder="{{ __('Search the market') }}">
                        <button type="submit" class="absolute inset-y-0 end-1 my-1 inline-flex items-center px-3 text-caption font-medium text-ink transition hover:text-brand">
                            {{ __('Search') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </header>

    <div x-show="open" x-transition.opacity x-cloak class="fixed inset-0 z-50 bg-ink-deep/45 lg:hidden" @click="closeDialog()"></div>
    <aside
        id="storefront-mobile-navigation"
        x-show="open"
        x-cloak
        x-ref="dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="storefront-mobile-navigation-title"
        tabindex="-1"
        x-transition:enter="transition duration-300 ease-brand"
        x-transition:enter-start="-translate-x-full rtl:translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full rtl:translate-x-full"
        class="fixed inset-y-0 start-0 z-50 flex w-[20rem] max-w-[88vw] flex-col bg-surface lg:hidden"
        @keydown="handleDialogKeydown($event)"
    >
        <div class="flex items-center justify-between border-b border-line px-5 py-4">
            <h2 id="storefront-mobile-navigation-title" class="font-display text-heading-3">{{ __('Menu') }}</h2>
            <button type="button" class="ds-icon-btn" @click="closeDialog()" aria-label="{{ __('Close menu') }}">×</button>
        </div>
        <nav class="flex-1 space-y-1 overflow-y-auto p-3" aria-label="{{ __('Categories') }}">
            <a href="{{ route('storefront.search') }}" class="block px-3 py-2.5 text-sm">{{ __('All') }}</a>
            @foreach ($navCategories as $category)
                <a href="{{ $category['url'] }}" class="block px-3 py-2.5 text-sm">{{ $category['name'] }}</a>
            @endforeach
            <a href="{{ route('home') }}#stores" class="block px-3 py-2.5 text-sm">{{ __('Stores') }}</a>
        </nav>
        <div class="space-y-3 border-t border-line p-5">
            <div class="flex items-center justify-between gap-3">
                <p class="text-[11px] uppercase tracking-[0.16em] text-ink-muted">{{ __('Language') }}</p>
                @include('partials.locale-switcher')
            </div>
            @auth
                <x-ui.button :href="route('dashboard')" variant="primary" class="w-full" type="button">{{ __('Account') }}</x-ui.button>
            @else
                <x-ui.button :href="route('login')" variant="secondary" class="w-full" type="button">{{ __('Log in') }}</x-ui.button>
                <x-ui.button :href="route('register')" variant="primary" class="w-full" type="button">{{ __('Join') }}</x-ui.button>
            @endauth
        </div>
    </aside>

    <main id="main" data-storefront-background>
        {{ $slot }}
    </main>

    <footer class="mt-20 bg-ink-deep text-ink-inverse" data-storefront-background>
        <div class="ds-container grid gap-10 py-16 sm:grid-cols-2 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <x-brand.logo inverse />
                <p class="mt-4 max-w-xs text-sm leading-relaxed text-ink-inverse/65">{{ __('A Syrian marketplace for considered everyday goods — designed Arabic-first, ready for trusted local stores.') }}</p>
            </div>
            <div class="lg:col-span-2">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-inverse/45">{{ __('Shop') }}</p>
                <ul class="mt-4 space-y-2.5 text-sm text-ink-inverse/75">
                    <li><a href="{{ route('storefront.search') }}">{{ __('All products') }}</a></li>
                    <li><a href="{{ route('home') }}#stores">{{ __('Stores') }}</a></li>
                    <li><a href="{{ route('storefront.search', ['sort' => 'newest']) }}">{{ __('New arrivals') }}</a></li>
                </ul>
            </div>
            <div class="lg:col-span-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-inverse/45">{{ __('Customer') }}</p>
                <ul class="mt-4 space-y-2.5 text-sm text-ink-inverse/75">
                    <li><a href="{{ route('cart.show') }}">{{ __('Cart') }}</a></li>
                    @auth
                        <li><a href="{{ route('dashboard') }}">{{ __('Account') }}</a></li>
                    @else
                        <li><a href="{{ route('login') }}">{{ __('Log in') }}</a></li>
                        <li><a href="{{ route('register') }}">{{ __('Register') }}</a></li>
                    @endauth
                </ul>
            </div>
            <div class="lg:col-span-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-inverse/45">{{ __('Sellers') }}</p>
                <ul class="mt-4 space-y-2.5 text-sm text-ink-inverse/75">
                    <li><a href="{{ route('register') }}">{{ __('Become a vendor') }}</a></li>
                    <li><a href="{{ route('vendor.dashboard') }}">{{ __('Seller workspace') }}</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-white/10">
            <div class="ds-container flex flex-wrap items-center justify-between gap-3 py-4 text-caption text-ink-inverse/45">
                <p>© {{ date('Y') }} {{ __('Sham Market') }}</p>
                <p>{{ __('Damascus · Arabic first · Local stores') }}</p>
            </div>
        </div>
    </footer>
</body>
</html>
