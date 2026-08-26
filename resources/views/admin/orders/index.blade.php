<x-admin-layout :title="__('Parent orders')">
    <x-ui.page-header :title="__('Parent orders')" :description="__('Read-only marketplace parent orders for staff operations.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Admin'), 'href' => route('admin.dashboard')],
                ['label' => __('Parent orders')],
            ]" />
        </x-slot:breadcrumb>
        <x-slot:actions>
            <x-ui.button :href="route('admin.vendor-orders.index')" variant="ghost">{{ __('Vendor orders') }}</x-ui.button>
            <x-ui.button :href="route('admin.payments.index')" variant="ghost">{{ __('Payments') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="ds-table-wrap">
        <table class="ds-table">
            <thead>
                <tr>
                    <th scope="col">{{ __('Order') }}</th>
                    <th scope="col">{{ __('Customer') }}</th>
                    <th scope="col">{{ __('Status') }}</th>
                    <th scope="col">{{ __('Placed') }}</th>
                    <th scope="col">{{ __('Vendor orders') }}</th>
                    <th scope="col">{{ __('COD dues') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            <a href="{{ route('admin.orders.show', $row['id']) }}" class="ds-link">{{ $row['public_code'] }}</a>
                        </td>
                        <td>
                            <p>{{ $row['customer_name'] }}</p>
                            <p class="text-caption text-ink-muted" dir="ltr">{{ $row['customer_email'] }}</p>
                        </td>
                        <td>{{ $row['status'] }}</td>
                        <td>{{ $row['placed_at_label'] }}</td>
                        <td>{{ $row['vendor_count'] }}</td>
                        <td>
                            @forelse ($row['cod_dues'] as $due)
                                <p class="tabular-nums" dir="ltr">{{ $due }}</p>
                            @empty
                                —
                            @endforelse
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10">
                            <x-ui.empty-state :title="__('No orders')">
                                {{ __('No parent orders yet.') }}
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
