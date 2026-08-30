<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Support\Locale::direction(app()->getLocale()) }}">
<head>
    @include('layouts.partials.head', ['title' => $title ?? __('Account')])
</head>
<body class="min-h-screen bg-canvas pb-20 lg:pb-0">
    @include('layouts.partials.toasts')

    <header class="border-b border-line bg-surface">
        <div class="ds-container flex h-16 items-center justify-between gap-4">
            <x-brand.logo />
            <div class="flex items-center gap-2">
                @include('partials.locale-switcher')
                @include('layouts.partials.notifications')
                @include('layouts.partials.user-menu')
            </div>
        </div>
        <nav class="ds-container hidden gap-7 overflow-x-auto text-sm lg:flex" aria-label="{{ __('Account') }}">
            <a href="{{ route('dashboard') }}" @class(['border-b-2 py-3', 'border-ink-deep text-ink' => request()->routeIs('dashboard'), 'border-transparent text-ink-muted hover:text-ink' => ! request()->routeIs('dashboard')])>{{ __('Overview') }}</a>
            <a href="{{ route('account.orders') }}" @class(['border-b-2 py-3', 'border-ink-deep text-ink' => request()->routeIs('account.orders*'), 'border-transparent text-ink-muted hover:text-ink' => ! request()->routeIs('account.orders*')])>{{ __('Orders') }}</a>
            <a href="{{ route('account.wishlist') }}" @class(['border-b-2 py-3', 'border-ink-deep text-ink' => request()->routeIs('account.wishlist'), 'border-transparent text-ink-muted hover:text-ink' => ! request()->routeIs('account.wishlist')])>{{ __('Wishlist') }}</a>
            <a href="{{ route('account.addresses') }}" @class(['border-b-2 py-3', 'border-ink-deep text-ink' => request()->routeIs('account.addresses'), 'border-transparent text-ink-muted hover:text-ink' => ! request()->routeIs('account.addresses')])>{{ __('Addresses') }}</a>
            <a href="{{ route('profile.edit') }}" @class(['border-b-2 py-3', 'border-ink-deep text-ink' => request()->routeIs('profile.*'), 'border-transparent text-ink-muted hover:text-ink' => ! request()->routeIs('profile.*')])>{{ __('Settings') }}</a>
            <a href="{{ route('home') }}" class="ms-auto border-b-2 border-transparent py-3 text-ink-muted hover:text-ink">{{ __('Back to shop') }}</a>
        </nav>
    </header>

    <main class="ds-container py-8 lg:py-12">
        @if (auth()->user()?->hasVerifiedEmail() === false)
            <x-ui.alert tone="warning" class="mb-8" :title="__('Your email address is unverified.')">
                <p>{{ __('You can use the storefront now; verify your email before applying as a vendor.') }}</p>
                <form method="POST" action="{{ route('verification.send') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="ds-link">{{ __('Resend verification email') }}</button>
                </form>
            </x-ui.alert>
        @endif
        {{ $slot }}
    </main>

    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface lg:hidden" aria-label="{{ __('Account') }}">
        <div class="grid grid-cols-5 text-[11px]">
            <a href="{{ route('dashboard') }}" class="flex flex-col items-center gap-1 py-2.5 {{ request()->routeIs('dashboard') ? 'text-ink' : 'text-ink-muted' }}">{{ __('Overview') }}</a>
            <a href="{{ route('account.orders') }}" class="flex flex-col items-center gap-1 py-2.5 {{ request()->routeIs('account.orders') ? 'text-ink' : 'text-ink-muted' }}">{{ __('Orders') }}</a>
            <a href="{{ route('account.wishlist') }}" class="flex flex-col items-center gap-1 py-2.5 {{ request()->routeIs('account.wishlist') ? 'text-ink' : 'text-ink-muted' }}">{{ __('Wishlist') }}</a>
            <a href="{{ route('account.addresses') }}" class="flex flex-col items-center gap-1 py-2.5 {{ request()->routeIs('account.addresses') ? 'text-ink' : 'text-ink-muted' }}">{{ __('Addresses') }}</a>
            <a href="{{ route('profile.edit') }}" class="flex flex-col items-center gap-1 py-2.5 {{ request()->routeIs('profile.*') ? 'text-ink' : 'text-ink-muted' }}">{{ __('Profile') }}</a>
        </div>
    </nav>
</body>
</html>
