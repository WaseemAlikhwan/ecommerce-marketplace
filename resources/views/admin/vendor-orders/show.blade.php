<x-admin-layout :title="$order['public_code']">
    <x-ui.page-header :title="$order['public_code']" :description="$order['store_name'] . ' · ' . $order['placed_at_label']">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Admin'), 'href' => route('admin.dashboard')],
                ['label' => __('Vendor orders'), 'href' => route('admin.vendor-orders.index')],
                ['label' => $order['public_code']],
            ]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    <div class="grid gap-8 lg:grid-cols-12">
        <div class="space-y-8 lg:col-span-8">
            <section class="border border-line bg-surface p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-display text-heading-3">{{ __('Status') }}</h2>
                        <p class="mt-2">{{ $order['status'] }}</p>
                    </div>
                    <div class="text-end text-sm">
                        <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Parent') }}</p>
                        <p class="mt-1">
                            @if ($order['parent_id'])
                                <a href="{{ route('admin.orders.show', $order['parent_id']) }}" class="ds-link">{{ $order['parent_public_code'] }}</a>
                            @else
                                {{ $order['parent_public_code'] }}
                            @endif
                        </p>
                    </div>
                </div>
            </section>

            <section class="border border-line bg-surface p-6">
                <h2 class="font-display text-heading-3">{{ __('Line items') }}</h2>
                <ul class="mt-6 space-y-3">
                    @foreach ($order['items'] as $item)
                        <li class="flex items-start justify-between gap-4 border-b border-line pb-3 last:border-0 last:pb-0">
                            <div>
                                <p class="font-medium">{{ $item['name'] }}</p>
                                <p class="mt-1 text-sm text-ink-muted">{{ __('Qty') }} {{ $item['quantity'] }} · {{ $item['unit_price_label'] }}</p>
                            </div>
                            <p class="ds-price shrink-0" dir="ltr">{{ $item['line_total_label'] }}</p>
                        </li>
                    @endforeach
                </ul>

                <dl class="mt-6 space-y-2 border-t border-line pt-4 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-muted">{{ __('Items') }}</dt>
                        <dd dir="ltr">{{ $order['items_subtotal_label'] }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-muted">{{ __('Shipping') }}</dt>
                        <dd dir="ltr">{{ $order['shipping_label'] }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-muted">{{ __('Discount') }}</dt>
                        <dd dir="ltr">{{ $order['discount_label'] }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-muted">{{ __('Commission') }} ({{ $order['commission_rate_bps'] }} {{ __('bps') }})</dt>
                        <dd dir="ltr">
                            {{ $order['commission_amount_label'] }}
                            @if ($order['commission_recognized'])
                                <span class="text-caption text-ink-muted">({{ __('Recognized') }})</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3 font-medium">
                        <dt>{{ __('Grand total') }}</dt>
                        <dd dir="ltr">{{ $order['grand_total_label'] }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <aside class="space-y-6 lg:col-span-4">
            <section class="border border-line bg-surface p-6">
                <h2 class="font-display text-heading-3">{{ __('Payment') }}</h2>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-muted">{{ __('Method') }}</dt>
                        <dd>{{ $order['payment_method'] }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-muted">{{ __('Status') }}</dt>
                        <dd>
                            @if ($order['payment_id'])
                                <a href="{{ route('admin.payments.show', $order['payment_id']) }}" class="ds-link">{{ $order['payment_status'] }}</a>
                            @else
                                {{ $order['payment_status'] }}
                            @endif
                        </dd>
                    </div>
                    @if ($order['payment_amount_label'])
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">{{ __('Amount') }}</dt>
                            <dd dir="ltr">{{ $order['payment_amount_label'] }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="border border-line bg-surface p-6">
                <h2 class="font-display text-heading-3">{{ __('Shipping address') }}</h2>
                <div class="mt-4 space-y-1 text-sm">
                    <p class="font-medium">{{ $order['shipping']['recipient_name'] }}</p>
                    <p dir="ltr">{{ $order['shipping']['phone'] }}</p>
                    <p>{{ $order['shipping']['lines'] }}</p>
                    <p>{{ $order['shipping']['locality'] }}</p>
                    <p>{{ $order['shipping']['country_code'] }}</p>
                    @if ($order['shipping']['notes'])
                        <p class="mt-3 text-ink-muted">{{ $order['shipping']['notes'] }}</p>
                    @endif
                </div>
            </section>
        </aside>
    </div>
</x-admin-layout>
