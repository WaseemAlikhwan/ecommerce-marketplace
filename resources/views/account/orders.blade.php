<x-app-layout :title="__('Orders')">
    <x-ui.page-header :title="__('Orders')" :description="__('Order history is a visual placeholder until checkout is implemented.')" />

    <x-ui.tabs
        active="all"
        :tabs="[
            ['key' => 'all', 'label' => __('All'), 'href' => route('account.orders')],
            ['key' => 'open', 'label' => __('Open'), 'href' => route('account.orders')],
            ['key' => 'completed', 'label' => __('Completed'), 'href' => route('account.orders')],
        ]"
    >
        <div class="border border-line bg-surface">
            <x-ui.empty-state :title="__('Your first order will live here')" :action="__('Browse products')" :href="route('home')">
                {{ __('Parent orders and vendor shipments will appear as a readable timeline — not a dense admin table.') }}
            </x-ui.empty-state>
        </div>
    </x-ui.tabs>
</x-app-layout>
