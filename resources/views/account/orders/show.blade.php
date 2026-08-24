<x-app-layout :title="__('Order') . ' ' . $order->publicCode">
    <x-ui.page-header :title="$order->publicCode" :description="__('Placed') . ' · ' . $order->placedAtLabel">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Orders'), 'href' => route('account.orders')],
                ['label' => $order->publicCode],
            ]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert tone="danger" class="mb-6">{{ $errors->first() }}</x-ui.alert>
    @endif

    <div class="grid gap-8 lg:grid-cols-12">
        <div class="space-y-8 lg:col-span-8">
            <section class="border border-line bg-surface p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-display text-heading-3">{{ __('Status') }}</h2>
                        <p class="mt-2">{{ $order->status }}</p>
                    </div>

                    @if ($canCancel ?? false)
                        <form method="POST" action="{{ route('account.orders.cancel', $order->id) }}" data-cancel-action>
                            @csrf
                            <x-ui.button type="submit" variant="danger">
                                {{ __('Cancel order') }}
                            </x-ui.button>
                        </form>
                    @endif
                </div>
            </section>

            @foreach ($order->vendorOrders as $vendorOrder)
                <section class="border border-line bg-surface p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ $vendorOrder['public_code'] }}</p>
                            <h2 class="mt-1 font-display text-heading-3">{{ $vendorOrder['store_name'] }}</h2>
                        </div>
                        <div class="text-end text-sm">
                            <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Shipment status') }}</p>
                            <p class="mt-1" data-vendor-shipment-status>{{ $vendorOrder['status'] }}</p>
                            <p class="mt-1 text-ink-muted">{{ $vendorOrder['payment_status'] }}</p>
                        </div>
                    </div>

                    <ul class="mt-6 space-y-3">
                        @foreach ($vendorOrder['items'] as $item)
                            <li class="flex items-start justify-between gap-4 border-b border-line pb-3 last:border-0 last:pb-0">
                                <div>
                                    <p class="font-medium">{{ $item['name'] }}</p>
                                    <p class="mt-1 text-sm text-ink-muted">{{ __('Qty') }} {{ $item['quantity'] }}</p>
                                </div>
                                <p class="ds-price shrink-0">{{ $item['line_total_label'] }}</p>
                            </li>
                        @endforeach
                    </ul>

                    <dl class="mt-6 space-y-2 border-t border-line pt-4 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">{{ __('Items') }}</dt>
                            <dd>{{ $vendorOrder['items_subtotal_label'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-ink-muted">{{ __('Shipping') }}</dt>
                            <dd>{{ $vendorOrder['shipping_label'] }}</dd>
                        </div>
                        <div class="flex justify-between gap-3 font-medium">
                            <dt>{{ __('Grand total') }}</dt>
                            <dd>{{ $vendorOrder['grand_total_label'] }}</dd>
                        </div>
                    </dl>
                </section>
            @endforeach
        </div>

        <aside class="space-y-6 lg:col-span-4">
            <section class="border border-line bg-surface p-6">
                <h2 class="font-display text-heading-3">{{ __('COD dues') }}</h2>
                <ul class="mt-4 space-y-2">
                    @foreach ($order->codDues as $due)
                        <li class="ds-price text-lg">{{ $due['label'] }}</li>
                    @endforeach
                </ul>
                <p class="mt-3 text-caption text-ink-muted">{{ __('Cash on delivery — pending collection.') }}</p>
            </section>

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
</x-app-layout>
