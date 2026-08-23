<x-app-layout :title="__('Account')">
    <div class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="ds-section-kicker">{{ __('Account') }}</p>
            <h1 class="mt-2 font-display text-heading-1">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</h1>
            <p class="mt-2 text-sm text-ink-muted">{{ auth()->user()->email }} · {{ auth()->user()->phone }}</p>
        </div>
        <x-ui.button :href="route('profile.edit')" variant="secondary" type="button">{{ __('Account settings') }}</x-ui.button>
    </div>

    <div class="mt-10 grid gap-8 lg:grid-cols-12">
        <section class="lg:col-span-7">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-display text-heading-3">{{ __('Recent activity') }}</h2>
                <a href="{{ route('account.orders') }}" class="text-caption text-ink-muted hover:text-ink">{{ __('All orders') }}</a>
            </div>
            <div class="border border-line bg-surface">
                <div class="flex items-center justify-between border-b border-line px-5 py-4">
                    <div>
                        <p class="text-label">{{ __('No orders yet') }}</p>
                        <p class="mt-1 text-caption text-ink-muted">{{ __('When checkout launches, this becomes a timeline of parent orders.') }}</p>
                    </div>
                    <x-ui.badge>{{ __('Empty') }}</x-ui.badge>
                </div>
                <div class="px-5 py-6">
                    <x-ui.button :href="route('home')" variant="primary" type="button">{{ __('Continue shopping') }}</x-ui.button>
                </div>
            </div>
        </section>

        <aside class="space-y-4 lg:col-span-5">
            <a href="{{ route('account.wishlist') }}" class="block border border-line bg-surface p-5 transition hover:border-ink/25">
                <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Wishlist') }}</p>
                <p class="mt-2 font-display text-heading-3">{{ __('Nothing saved') }}</p>
                <p class="mt-1 text-sm text-ink-muted">{{ __('Heart a piece on the storefront to preview this list later.') }}</p>
            </a>
            <a href="{{ route('account.addresses') }}" class="block border border-line bg-surface p-5 transition hover:border-ink/25">
                <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Addresses') }}</p>
                <p class="mt-2 font-display text-heading-3">{{ __('No address book') }}</p>
                <p class="mt-1 text-sm text-ink-muted">{{ __('Shipping addresses wait for the commerce phase.') }}</p>
            </a>
            @if (auth()->user()->canAccessVendorPanel())
                <a href="{{ route('vendor.dashboard') }}" class="block border border-line bg-surface px-4 py-3 text-caption hover:border-ink/25">{{ __('Seller workspace') }}</a>
            @else
                <a href="{{ route('account.vendor-application') }}" class="block border border-line bg-surface px-4 py-3 text-caption hover:border-ink/25">{{ __('Become a vendor') }}</a>
            @endif
            @if (auth()->user()->isStaff())
                <a href="{{ route('admin.dashboard') }}" class="block border border-line bg-surface px-4 py-3 text-caption hover:border-ink/25">{{ __('Admin console') }}</a>
            @endif
        </aside>
    </div>
</x-app-layout>
