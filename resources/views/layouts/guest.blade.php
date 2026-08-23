<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Locale::direction(app()->getLocale()) }}">
<head>
    @include('layouts.partials.head', ['title' => $title ?? config('app.name')])
</head>
<body class="min-h-screen bg-canvas">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-50 focus:bg-elevated focus:px-3 focus:py-2">{{ __('Skip to content') }}</a>
    @include('layouts.partials.toasts')
    <div class="grid min-h-screen lg:grid-cols-12">
        <aside class="relative hidden overflow-hidden bg-ink-deep lg:col-span-5 lg:block">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_25%_20%,rgba(196,132,29,0.34),transparent_28%),radial-gradient(circle_at_80%_65%,rgba(255,255,255,0.12),transparent_34%)]"></div>
            <div class="absolute inset-0 ds-pattern opacity-20"></div>
            <svg viewBox="0 0 500 800" class="absolute inset-0 h-full w-full text-white/10" fill="none" aria-hidden="true">
                <path d="M250 90c110 0 200 90 200 200v420H50V290c0-110 90-200 200-200Z" stroke="currentColor" stroke-width="2"/>
                <path d="M115 710V330c0-75 60-135 135-135s135 60 135 135v380M250 195v515M115 420h270" stroke="currentColor"/>
            </svg>
            <div class="relative flex h-full flex-col justify-between p-10 text-ink-inverse">
                <x-brand.logo inverse />
                <div>
                    <p class="text-[11px] uppercase tracking-[0.2em] text-accent">{{ __('Sham Market') }}</p>
                    <p class="mt-4 max-w-sm font-display text-heading-1">{{ __('An account for the house, and later for the store.') }}</p>
                </div>
            </div>
        </aside>

        <main id="main" class="flex flex-col lg:col-span-7">
            <div class="flex items-center justify-between px-5 py-5 sm:px-10">
                <div class="lg:hidden">
                    <x-brand.logo />
                </div>
                <div class="ms-auto flex items-center gap-4">
                    @include('partials.locale-switcher')
                    <a href="{{ route('home') }}" class="text-sm text-ink-muted hover:text-ink">{{ __('Back to shop') }}</a>
                </div>
            </div>
            <div class="mx-auto flex w-full max-w-[28rem] flex-1 flex-col justify-center px-5 py-8 sm:px-0">
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
