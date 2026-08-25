<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Locale::direction(app()->getLocale()) }}">
<head>
    @include('layouts.partials.head', ['title' => $title ?? __('Admin')])
</head>
<body class="min-h-screen bg-canvas" x-data="{ sidebarOpen: false }">
    @include('layouts.partials.toasts')
    <div class="flex min-h-screen">
        <aside class="workspace-rail hidden lg:flex">
            <div class="flex h-16 items-center px-5">
                <x-brand.logo inverse />
            </div>
            <p class="px-5 pb-3 text-[10px] uppercase tracking-[0.18em] text-ink-inverse/35">{{ __('Operations') }}</p>
            <nav class="flex-1 space-y-0.5 px-3" aria-label="{{ __('Admin') }}">
                <x-ui.nav-link tone="dark" :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">{{ __('Overview') }}</x-ui.nav-link>
                <x-ui.nav-link tone="dark" :href="route('admin.vendors')" :active="request()->routeIs('admin.vendors')">{{ __('Vendors') }}</x-ui.nav-link>
                <x-ui.nav-link tone="dark" :href="route('admin.reviews.index')" :active="request()->routeIs('admin.reviews.*')">{{ __('Product reviews') }}</x-ui.nav-link>
                <x-ui.nav-link tone="dark" :href="route('admin.coupons.index')" :active="request()->routeIs('admin.coupons.*')">{{ __('Coupons') }}</x-ui.nav-link>
                <x-ui.nav-link tone="dark" :href="route('admin.catalog')" :active="request()->routeIs('admin.catalog', 'admin.categories.*', 'admin.brands.*', 'admin.attributes.*', 'admin.attribute-values.*')">{{ __('Catalog') }}</x-ui.nav-link>
                <x-ui.nav-link tone="dark" :href="route('admin.orders')" :active="request()->routeIs('admin.orders')">{{ __('Orders') }}</x-ui.nav-link>
                <x-ui.nav-link tone="dark" :href="route('admin.settings')" :active="request()->routeIs('admin.settings')">{{ __('Settings') }}</x-ui.nav-link>
            </nav>
            <div class="p-3">
                <x-ui.nav-link tone="dark" :href="route('home')">{{ __('Back to shop') }}</x-ui.nav-link>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-16 items-center gap-3 border-b border-line bg-surface px-4 sm:px-6">
                <button type="button" class="ds-icon-btn lg:hidden" @click="sidebarOpen = true" aria-label="{{ __('Open menu') }}">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                </button>
                <label class="sr-only" for="admin-command">{{ __('Search') }}</label>
                <div class="relative min-w-0 flex-1">
                    <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-ink-muted">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="1.6"/><path d="M16 16l4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    </span>
                    <input id="admin-command" type="search" class="ds-search bg-canvas" placeholder="{{ __('Jump to vendors, catalog, orders…') }}" disabled>
                </div>
                @include('partials.locale-switcher')
                @include('layouts.partials.notifications')
                @include('layouts.partials.user-menu')
            </header>
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    <div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-50 bg-ink-deep/45 lg:hidden" @click="sidebarOpen = false"></div>
    <aside x-show="sidebarOpen" x-cloak class="fixed inset-y-0 start-0 z-50 w-72 bg-ink-deep p-4 text-ink-inverse lg:hidden">
        <div class="mb-6 flex items-center justify-between">
            <x-brand.logo inverse />
            <button type="button" class="text-ink-inverse" @click="sidebarOpen = false" aria-label="{{ __('Close menu') }}">×</button>
        </div>
        <nav class="space-y-1" aria-label="{{ __('Admin') }}">
            <x-ui.nav-link tone="dark" :href="route('admin.dashboard')">{{ __('Overview') }}</x-ui.nav-link>
            <x-ui.nav-link tone="dark" :href="route('admin.vendors')">{{ __('Vendors') }}</x-ui.nav-link>
            <x-ui.nav-link tone="dark" :href="route('admin.reviews.index')">{{ __('Product reviews') }}</x-ui.nav-link>
            <x-ui.nav-link tone="dark" :href="route('admin.coupons.index')">{{ __('Coupons') }}</x-ui.nav-link>
            <x-ui.nav-link tone="dark" :href="route('admin.catalog')">{{ __('Catalog') }}</x-ui.nav-link>
            <x-ui.nav-link tone="dark" :href="route('admin.orders')">{{ __('Orders') }}</x-ui.nav-link>
            <x-ui.nav-link tone="dark" :href="route('admin.settings')">{{ __('Settings') }}</x-ui.nav-link>
        </nav>
        <div class="mt-6 border-t border-white/10 pt-4">
            <p class="mb-2 text-[11px] uppercase tracking-[0.16em] text-ink-inverse/45">{{ __('Language') }}</p>
            @include('partials.locale-switcher', ['inverse' => true])
        </div>
    </aside>
</body>
</html>
