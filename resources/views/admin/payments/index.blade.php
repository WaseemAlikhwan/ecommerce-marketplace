<x-admin-layout :title="__('Payments')">
    <x-ui.page-header :title="__('Payments')" :description="__('Read-only COD payment records. Collection remains out of admin V1.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Admin'), 'href' => route('admin.dashboard')],
                ['label' => __('Payments')],
            ]" />
        </x-slot:breadcrumb>
        <x-slot:actions>
            <x-ui.button :href="route('admin.orders')" variant="ghost">{{ __('Parent orders') }}</x-ui.button>
            <x-ui.button :href="route('admin.vendor-orders.index')" variant="ghost">{{ __('Vendor orders') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="ds-table-wrap">
        <table class="ds-table">
            <thead>
                <tr>
                    <th scope="col">{{ __('Payment') }}</th>
                    <th scope="col">{{ __('Vendor order') }}</th>
                    <th scope="col">{{ __('Method') }}</th>
                    <th scope="col">{{ __('Status') }}</th>
                    <th scope="col">{{ __('Amount') }}</th>
                    <th scope="col">{{ __('Collected') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            <a href="{{ route('admin.payments.show', $row['id']) }}" class="ds-link">#{{ $row['id'] }}</a>
                        </td>
                        <td>
                            <a href="{{ route('admin.vendor-orders.show', $row['vendor_order_id']) }}" class="ds-link">{{ $row['vendor_order_code'] }}</a>
                        </td>
                        <td>{{ $row['method'] }}</td>
                        <td>{{ $row['status'] }}</td>
                        <td class="tabular-nums" dir="ltr">{{ $row['amount_label'] }}</td>
                        <td>{{ $row['collected_at_label'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10">
                            <x-ui.empty-state :title="__('No payments')">
                                {{ __('No payments yet.') }}
                            </x-ui.empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($payments->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $payments->links() }}</div>
        @endif
    </div>
</x-admin-layout>
