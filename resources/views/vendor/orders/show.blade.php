<x-vendor-layout :title="__('Order') . ' ' . $order->publicCode">
    <x-slot name="header">{{ $order->publicCode }}</x-slot>

    <x-ui.page-header :title="$order->publicCode" :description="__('Parent') . ' ' . $order->parentPublicCode . ' · ' . $order->placedAtLabel">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Vendor'), 'href' => route('vendor.dashboard')],
                ['label' => __('Orders'), 'href' => route('vendor.orders')],
                ['label' => $order->publicCode],
            ]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    <div class="grid gap-8 lg:grid-cols-12">
        <div class="space-y-8 lg:col-span-8">
            <section class="border border-line bg-surface p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Status') }}</p>
                        <p class="mt-1 font-display text-heading-3">{{ $order->status }}</p>
                    </div>
                    <div class="text-end text-sm">
                        <p>{{ $order->paymentMethod }}</p>
                        <p class="mt-1 text-ink-muted">{{ $order->paymentStatus }}</p>
                    </div>
                </div>

                <ul class="mt-6 space-y-3">
                    @foreach ($order->items as $item)
                        <li class="flex items-start justify-between gap-4 border-b border-line pb-3 last:border-0 last:pb-0">
                            <div>
                                <p class="font-medium">{{ $item['name'] }}</p>
                                <p class="mt-1 text-sm text-ink-muted">{{ __('Qty') }} {{ $item['quantity'] }} · {{ $item['unit_price_label'] }}</p>
                            </div>
                            <p class="ds-price shrink-0">{{ $item['line_total_label'] }}</p>
                        </li>
                    @endforeach
                </ul>

                <dl class="mt-6 space-y-2 border-t border-line pt-4 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-muted">{{ __('Items') }}</dt>
                        <dd>{{ $order->itemsSubtotalLabel }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-muted">{{ __('Shipping') }}</dt>
                        <dd>{{ $order->shippingLabel }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 font-medium">
                        <dt>{{ __('Grand total') }}</dt>
                        <dd>{{ $order->grandTotalLabel }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <aside class="lg:col-span-4">
            <section class="border border-line bg-surface p-6">
                <h2 class="font-display text-heading-3">{{ __('Shipping address') }}</h2>
                <div class="mt-4 space-y-1 text-sm">
                    <p class="font-medium">{{ $order->shipping['recipient_name'] }}</p>
                    <p>{{ $order->shipping['phone'] }}</p>
                    <p>{{ $order->shipping['lines'] }}</p>
                    <p>{{ $order->shipping['locality'] }}</p>
                    <p>{{ $order->shipping['country_code'] }}</p>
                    @if ($order->shipping['notes'])
                        <p class="mt-3 text-ink-muted">{{ $order->shipping['notes'] }}</p>
                    @endif
                </div>
            </section>
        </aside>
    </div>
</x-vendor-layout>
