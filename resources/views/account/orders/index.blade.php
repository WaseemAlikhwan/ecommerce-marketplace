<x-app-layout :title="__('Orders')">
    <x-ui.page-header :title="__('Orders')" :description="__('Your parent orders with nested vendor shipments and COD dues.')" />

    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    @if ($rows === [])
        <div class="border border-line bg-surface">
            <x-ui.empty-state :title="__('Your first order will live here')" :action="__('Browse products')" :href="route('storefront.search')">
                {{ __('Parent orders and vendor shipments will appear here after checkout.') }}
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
                        <th scope="col">{{ __('Vendors') }}</th>
                        <th scope="col">{{ __('COD dues') }}</th>
                        <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="font-medium">{{ $row['public_code'] }}</td>
                            <td>{{ $row['placed_at_label'] }}</td>
                            <td>{{ $row['status'] }}</td>
                            <td>{{ $row['vendor_count'] }}</td>
                            <td>
                                <ul class="space-y-1">
                                    @foreach ($row['cod_dues'] as $due)
                                        <li>{{ $due }}</li>
                                    @endforeach
                                </ul>
                            </td>
                            <td>
                                <x-ui.button :href="route('account.orders.show', $row['id'])" variant="secondary" size="sm" type="button">{{ __('View') }}</x-ui.button>
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
</x-app-layout>
