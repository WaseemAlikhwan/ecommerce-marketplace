@php
    use App\Cart\CartMergeUnavailable;
    use App\Cart\CartViewLine;
    use App\Support\Money;

    $formatMoney = static function (?array $money): ?string {
        if ($money === null) {
            return null;
        }

        return Money::formatFromMinor((int) $money['amount_minor'], (int) $money['exponent'])
            .' '.$money['currency_code'];
    };

    $unavailableLabel = static function (?string $reason): string {
        return match ($reason) {
            CartMergeUnavailable::OUT_OF_STOCK => __('This item is out of stock.'),
            default => __('This item is no longer available.'),
        };
    };
@endphp

<x-storefront-layout
    :title="__('Cart')"
    :description="__('Review your bag before checkout.')"
    :canonical="route('cart.show')"
    robots="noindex,follow"
    :nav-categories="$navCategories"
>
    <div class="ds-container py-8 md:py-14">
        <x-ui.breadcrumb :items="[
            ['label' => __('Shop'), 'href' => route('storefront.search')],
            ['label' => __('Cart')],
        ]" />

        <div class="mt-8 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-ink-muted">{{ __('Your bag') }}</p>
                <h1 class="mt-2 font-display text-heading-1 tracking-tight">{{ __('Cart') }}</h1>
                <p class="mt-2 max-w-xl text-sm text-ink-muted">{{ __('Prices are informational until checkout. Mixed currencies stay separate — no conversion here.') }}</p>
            </div>
            <x-ui.button :href="route('storefront.search')" variant="secondary" size="sm" type="button">{{ __('Continue shopping') }}</x-ui.button>
        </div>

        @if (session('status'))
            <div class="mt-6">
                <x-ui.alert tone="success">{{ session('status') }}</x-ui.alert>
            </div>
        @endif

        @if ($errors->has('cart'))
            <div class="mt-6">
                <x-ui.alert tone="danger">{{ $errors->first('cart') }}</x-ui.alert>
            </div>
        @endif

        @include('storefront.partials.cart-merge-flash', ['merge' => $merge])

        @if ($cart->isEmpty())
            <div class="mt-12 border border-line bg-surface">
                <x-ui.empty-state
                    :title="__('Your cart is empty')"
                    :action="__('Browse products')"
                    :href="route('storefront.search')"
                >
                    {{ __('Add something you like — it will wait here while you browse.') }}
                </x-ui.empty-state>
            </div>
        @else
            <div class="mt-10 grid gap-10 lg:grid-cols-12 lg:gap-14">
                <div class="space-y-4 lg:col-span-8">
                    @foreach ($cart->lines as $line)
                        @include('storefront.partials.cart-line', [
                            'line' => $line,
                            'formatMoney' => $formatMoney,
                            'unavailableLabel' => $unavailableLabel,
                        ])
                    @endforeach
                </div>

                <aside class="lg:col-span-4">
                    <div class="border border-line bg-surface p-6 lg:sticky lg:top-28">
                        <h2 class="font-display text-heading-3">{{ __('Summary') }}</h2>
                        <p class="mt-2 text-sm text-ink-muted">{{ __('Subtotals by currency') }}</p>

                        <dl class="mt-6 space-y-4">
                            @forelse ($cart->subtotals as $subtotal)
                                <div class="flex items-baseline justify-between gap-4 border-b border-line pb-4 last:border-0 last:pb-0">
                                    <dt class="text-sm text-ink-muted">{{ __('Subtotal') }} · {{ $subtotal->currencyCode }}</dt>
                                    <dd class="ds-price text-lg">{{ $formatMoney($subtotal->total) }}</dd>
                                </div>
                            @empty
                                <p class="text-sm text-ink-muted">{{ __('No payable lines yet.') }}</p>
                            @endforelse
                        </dl>

                        <div class="mt-8 space-y-3">
                            @auth
                                <x-ui.button
                                    variant="primary"
                                    class="w-full"
                                    type="button"
                                    disabled
                                    aria-disabled="true"
                                    title="{{ __('Checkout opens in a later phase') }}"
                                >{{ __('Continue to checkout') }}</x-ui.button>
                                <p class="text-center text-caption text-ink-muted">{{ __('Checkout opens in a later phase') }}</p>
                            @else
                                <x-ui.button :href="route('login')" variant="primary" class="w-full" type="button">{{ __('Log in to continue') }}</x-ui.button>
                                <p class="text-center text-caption text-ink-muted">{{ __('Sign in to keep your bag and continue when checkout opens.') }}</p>
                            @endauth
                        </div>
                    </div>
                </aside>
            </div>
        @endif
    </div>
</x-storefront-layout>
