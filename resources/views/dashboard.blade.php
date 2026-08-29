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
            @if ($recentOrders === [])
                <div class="border border-line bg-surface">
                    <x-ui.empty-state :title="__('Your first order will live here')" :action="__('Browse products')" :href="route('storefront.search')">
                        {{ __('Parent orders and vendor shipments will appear here after checkout.') }}
                    </x-ui.empty-state>
                </div>
            @else
                <ul class="divide-y divide-line border border-line bg-surface">
                    @foreach ($recentOrders as $order)
                        <li>
                            <a href="{{ route('account.orders.show', $order['id']) }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 transition hover:bg-canvas">
                                <div>
                                    <p class="font-medium">{{ $order['public_code'] }}</p>
                                    <p class="mt-1 text-caption text-ink-muted">{{ $order['placed_at_label'] }}</p>
                                </div>
                                <x-ui.badge>{{ $order['status'] }}</x-ui.badge>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <aside class="space-y-4 lg:col-span-5">
            <a href="{{ route('account.wishlist') }}" class="block border border-line bg-surface p-5 transition hover:border-ink/25">
                <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Wishlist') }}</p>
                <p class="mt-2 font-display text-heading-3">
                    @if ($wishlistCount > 0)
                        {{ trans_choice(':count saved product|:count saved products', $wishlistCount, ['count' => $wishlistCount]) }}
                    @else
                        {{ __('Your wishlist is empty') }}
                    @endif
                </p>
                @if ($wishlistCount === 0)
                    <p class="mt-1 text-sm text-ink-muted">{{ __('Save products while you browse the shop.') }}</p>
                @endif
            </a>
            <a href="{{ route('account.addresses') }}" class="block border border-line bg-surface p-5 transition hover:border-ink/25">
                <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Addresses') }}</p>
                <p class="mt-2 font-display text-heading-3">
                    @if ($addressCount > 0)
                        {{ trans_choice(':count saved address|:count saved addresses', $addressCount, ['count' => $addressCount]) }}
                    @else
                        {{ __('No addresses saved') }}
                    @endif
                </p>
                <p class="mt-1 text-sm text-ink-muted">{{ __('Manage Syria delivery addresses for checkout.') }}</p>
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
