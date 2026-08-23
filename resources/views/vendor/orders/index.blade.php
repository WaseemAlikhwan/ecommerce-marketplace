<x-vendor-layout :title="__('Orders')">
    <x-slot name="header">{{ __('Orders') }}</x-slot>

    <x-ui.page-header :title="__('Orders')" :description="__('Vendor orders for your store only.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Vendor'), 'href' => route('vendor.dashboard')],
                ['label' => __('Orders')],
            ]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    @if ($rows === [])
        <div class="border border-line bg-surface">
            <x-ui.empty-state :title="__('No orders yet')">
                {{ __('New customer orders will appear here after checkout.') }}
            </x-ui.empty-state>
        </div>
    @else
        <div class="ds-table-wrap">
            <table class="ds-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Order') }}</th>
                        <th scope="col">{{ __('Placed') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th scope="col">{{ __('Payment') }}</th>
                        <th scope="col">{{ __('Total') }}</th>
                        <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="font-medium">{{ $row['public_code'] }}</td>
                            <td>{{ $row['placed_at_label'] }}</td>
                            <td>{{ $row['status'] }}</td>
                            <td>{{ $row['payment_status'] }}</td>
                            <td>{{ $row['grand_total_label'] }}</td>
                            <td>
                                <x-ui.button :href="route('vendor.orders.show', $row['id'])" variant="secondary" size="sm" type="button">{{ __('View') }}</x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $orders->links() }}
        </div>
    @endif
</x-vendor-layout>
