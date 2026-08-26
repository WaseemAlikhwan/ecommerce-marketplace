<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Locale::direction(app()->getLocale()) }}">
<head>
    @include('layouts.partials.head', ['title' => $title ?? config('app.name'), 'robots' => 'noindex,follow'])
</head>
<body class="min-h-screen bg-canvas">
    <div class="ds-container flex min-h-screen flex-col py-10">
        <header class="flex items-center justify-between gap-4">
            <x-brand.logo />
            <div class="flex items-center gap-4">
                @include('partials.locale-switcher')
                <a href="{{ route('home') }}" class="text-sm text-ink-muted hover:text-ink">{{ __('Back to shop') }}</a>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-lg flex-1 flex-col justify-center py-16 text-center">
            <p class="text-[11px] uppercase tracking-[0.2em] text-ink-muted">{{ $code }}</p>
            <h1 class="mt-3 font-display text-heading-1 text-ink">{{ $heading }}</h1>
            <p class="mt-4 text-body text-ink-muted">{{ $message }}</p>
            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                <x-ui.button :href="route('home')" variant="primary">{{ __('Back to shop') }}</x-ui.button>
                @isset($secondaryHref)
                    <x-ui.button :href="$secondaryHref" variant="ghost">{{ $secondaryLabel }}</x-ui.button>
                @endisset
            </div>
        </main>
    </div>
</body>
</html>
