<x-admin-layout :title="__('Payment') . ' #' . $payment['id']">
    <x-ui.page-header :title="__('Payment') . ' #' . $payment['id']" :description="$payment['method']">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Admin'), 'href' => route('admin.dashboard')],
                ['label' => __('Payments'), 'href' => route('admin.payments.index')],
                ['label' => '#' . $payment['id']],
            ]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    <section class="max-w-xl border border-line bg-surface p-6">
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between gap-3">
                <dt class="text-ink-muted">{{ __('Status') }}</dt>
                <dd>{{ $payment['status'] }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-ink-muted">{{ __('Method') }}</dt>
                <dd>{{ $payment['method'] }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-ink-muted">{{ __('Amount') }}</dt>
                <dd class="tabular-nums" dir="ltr">{{ $payment['amount_label'] }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-ink-muted">{{ __('Collected') }}</dt>
                <dd>{{ $payment['collected_at_label'] }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-ink-muted">{{ __('Vendor order') }}</dt>
                <dd>
                    <a href="{{ route('admin.vendor-orders.show', $payment['vendor_order_id']) }}" class="ds-link">{{ $payment['vendor_order_code'] }}</a>
                </dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-ink-muted">{{ __('Parent') }}</dt>
                <dd>
                    @if ($payment['parent_id'])
                        <a href="{{ route('admin.orders.show', $payment['parent_id']) }}" class="ds-link">{{ $payment['parent_public_code'] }}</a>
                    @else
                        {{ $payment['parent_public_code'] }}
                    @endif
                </dd>
            </div>
        </dl>
    </section>
</x-admin-layout>
