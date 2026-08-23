<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Locale::direction(app()->getLocale()) }}">
<head>
    @include('layouts.partials.head', ['title' => $title ?? __('Vendor')])
</head>
<body class="min-h-screen bg-canvas" x-data="{ sidebarOpen: false }">
    @include('layouts.partials.toasts')
    <div class="flex min-h-screen">
        <aside class="workspace-rail hidden lg:flex">
            <div class="flex h-16 items-center px-5">
                <x-brand.logo inverse />
            </div>
            <div class="mx-4 mb-4 border border-white/10 p-3">
                <p class="text-[10px] uppercase tracking-[0.16em] text-ink-inverse/40">{{ __('Store identity') }}</p>
                <p class="mt-1 text-sm font-medium">{{ auth()->user()->vendor?->store?->name ?? __('Your store') }}</p>
                <p class="text-[11px] text-ink-inverse/45">{{ auth()->user()->vendor?->store?->status?->value === 'active' ? __('Active') : __('Suspended') }}</p>
            </div>
            <nav class="flex-1 space-y-0.5 px-3" aria-label="{{ __('Vendor') }}">
                <x-ui.nav-link tone="dark" :href="route('vendor.dashboard')" :active="request()->routeIs('vendor.dashboard')">{{ __('Overview') }}</x-ui.nav-link>
                <x-ui.nav-link tone="dark" :href="route('vendor.products')" :active="request()->routeIs('vendor.products*')">{{ __('Products') }}</x-ui.nav-link>
                <x-ui.nav-link tone="dark" :href="route('vendor.orders')" :active="request()->routeIs('vendor.orders*')">{{ __('Orders') }}</x-ui.nav-link>
                <x-ui.nav-link tone="dark" :href="route('vendor.store')" :active="request()->routeIs('vendor.store')">{{ __('Store profile') }}</x-ui.nav-link>
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
                <div class="min-w-0 flex-1">
                    <p class="truncate text-label">{{ $header ?? __('Seller workspace') }}</p>
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
            <button type="button" @click="sidebarOpen = false" aria-label="{{ __('Close menu') }}">×</button>
        </div>
        <nav class="space-y-1" aria-label="{{ __('Vendor') }}">
            <x-ui.nav-link tone="dark" :href="route('vendor.dashboard')">{{ __('Overview') }}</x-ui.nav-link>
            <x-ui.nav-link tone="dark" :href="route('vendor.products')">{{ __('Products') }}</x-ui.nav-link>
            <x-ui.nav-link tone="dark" :href="route('vendor.orders')">{{ __('Orders') }}</x-ui.nav-link>
            <x-ui.nav-link tone="dark" :href="route('vendor.store')">{{ __('Store profile') }}</x-ui.nav-link>
        </nav>
        <div class="mt-6 border-t border-white/10 pt-4">
            <p class="mb-2 text-[11px] uppercase tracking-[0.16em] text-ink-inverse/45">{{ __('Language') }}</p>
            @include('partials.locale-switcher', ['inverse' => true])
        </div>
    </aside>
</body>
</html>
