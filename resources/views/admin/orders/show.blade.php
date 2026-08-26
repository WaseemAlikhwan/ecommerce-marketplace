<x-admin-layout :title="$order['public_code']">
    <x-ui.page-header :title="$order['public_code']" :description="__('Placed') . ' · ' . $order['placed_at_label']">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Admin'), 'href' => route('admin.dashboard')],
                ['label' => __('Parent orders'), 'href' => route('admin.orders')],
                ['label' => $order['public_code']],
            ]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    <div class="grid gap-8 lg:grid-cols-12">
        <div class="space-y-8 lg:col-span-8">
            <section class="border border-line bg-surface p-6">
                <h2 class="font-display text-heading-3">{{ __('Status') }}</h2>
                <p class="mt-2">{{ $order['status'] }}</p>
                <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-ink-muted">{{ __('Customer') }}</dt>
                        <dd class="mt-1">{{ $order['customer_name'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-muted">{{ __('Email') }}</dt>
                        <dd class="mt-1" dir="ltr">{{ $order['customer_email'] }}</dd>
                    </div>
                </dl>
            </section>

            @foreach ($order['vendor_orders'] as $vendorOrder)
                <section class="border border-line bg-surface p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">
                                <a href="{{ route('admin.vendor-orders.show', $vendorOrder['id']) }}" class="ds-link">{{ $vendorOrder['public_code'] }}</a>
                            </p>
                            <h2 class="mt-1 font-display text-heading-3">{{ $vendorOrder['store_name'] }}</h2>
                        </div>
                        <div class="text-end text-sm">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Shipment status') }}</p>
                            <p class="mt-1">{{ $vendorOrder['status'] }}</p>
                            <p class="mt-1 text-ink-muted">
                                @if ($vendorOrder['payment_id'])
                                    <a href="{{ route('admin.payments.show', $vendorOrder['payment_id']) }}" class="ds-link">{{ $vendorOrder['payment_status'] }}</a>
                                @else
                                    {{ $vendorOrder['payment_status'] }}
                                @endif
                            </p>
                        </div>
                    </div>

                    <ul class="mt-6 space-y-3">
                        @foreach ($vendorOrder['items'] as $item)
                            <li class="flex items-start justify-between gap-4 border-b border-line pb-3 last:border-0 last:pb-0">
                                <div>
                                    <p class="font-medium">{{ $item['name'] }}</p>
                                    <p class="mt-1 text-sm text-ink-muted">{{ __('Qty') }} {{ $item['quantity'] }}</p>
                                </div>
                                <p class="ds-price shrink-0" dir="ltr">{{ $item['line_total_label'] }}</p>
                            </li>
                        @endforeach
                    </ul>

                    <dl class="mt-6 space-y-2 border-t border-line pt-4 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">{{ __('Items') }}</dt>
                            <dd dir="ltr">{{ $vendorOrder['items_subtotal_label'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">{{ __('Shipping') }}</dt>
                            <dd dir="ltr">{{ $vendorOrder['shipping_label'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">{{ __('Commission') }}</dt>
                            <dd dir="ltr">
                                {{ $vendorOrder['commission_amount_label'] }}
                                @if ($vendorOrder['commission_recognized'])
                                    <span class="text-caption text-ink-muted">({{ __('Recognized') }})</span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3 font-medium">
                            <dt>{{ __('Grand total') }}</dt>
                            <dd dir="ltr">{{ $vendorOrder['grand_total_label'] }}</dd>
                        </div>
                    </dl>
                </section>
            @endforeach
        </div>

        <aside class="space-y-6 lg:col-span-4">
            <section class="border border-line bg-surface p-6">
                <h2 class="font-display text-heading-3">{{ __('COD dues') }}</h2>
                <ul class="mt-4 space-y-2">
                    @foreach ($order['cod_dues'] as $due)
                        <li class="ds-price text-lg" dir="ltr">{{ $due['label'] }}</li>
                    @endforeach
                </ul>
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
