<x-admin-layout :title="__('Vendor orders')">
    <x-ui.page-header :title="__('Vendor orders')" :description="__('Read-only vendor order shipments for staff operations.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Admin'), 'href' => route('admin.dashboard')],
                ['label' => __('Vendor orders')],
            ]" />
        </x-slot:breadcrumb>
        <x-slot:actions>
            <x-ui.button :href="route('admin.orders')" variant="ghost">{{ __('Parent orders') }}</x-ui.button>
            <x-ui.button :href="route('admin.payments.index')" variant="ghost">{{ __('Payments') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="ds-table-wrap">
        <table class="ds-table">
            <thead>
                <tr>
                    <th scope="col">{{ __('Order') }}</th>
                    <th scope="col">{{ __('Parent') }}</th>
                    <th scope="col">{{ __('Store') }}</th>
                    <th scope="col">{{ __('Status') }}</th>
                    <th scope="col">{{ __('Payment') }}</th>
                    <th scope="col">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            <a href="{{ route('admin.vendor-orders.show', $row['id']) }}" class="ds-link">{{ $row['public_code'] }}</a>
                        </td>
                        <td>
                            @if ($row['parent_id'])
                                <a href="{{ route('admin.orders.show', $row['parent_id']) }}" class="ds-link">{{ $row['parent_public_code'] }}</a>
                            @else
                                {{ $row['parent_public_code'] }}
                            @endif
                        </td>
                        <td>{{ $row['store_name'] }}</td>
                        <td>{{ $row['status'] }}</td>
                        <td>
                            @if ($row['payment_id'])
                                <a href="{{ route('admin.payments.show', $row['payment_id']) }}" class="ds-link">{{ $row['payment_status'] }}</a>
                            @else
                                {{ $row['payment_status'] }}
                            @endif
                        </td>
                        <td class="tabular-nums" dir="ltr">{{ $row['grand_total_label'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10">
                            <x-ui.empty-state :title="__('No orders')">
                                {{ __('No vendor orders yet.') }}
                            </x-ui.empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($orders->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $orders->links() }}</div>
        @endif
    </div>
</x-admin-layout>
