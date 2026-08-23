@php
    $pages = [
        'vendor.products' => [
            'title' => __('Products'),
            'columns' => [__('Product'), __('Status'), __('Price'), __('Updated')],
            'description' => __('Product and variant management will use this workspace later.'),
        ],
        'vendor.orders' => [
            'title' => __('Orders'),
            'columns' => [__('Order'), __('Customer'), __('Status'), __('Total')],
            'description' => __('Vendor-order fulfillment will appear here later.'),
        ],
        'vendor.store' => [
            'title' => __('Store profile'),
            'columns' => [__('Field'), __('Value')],
            'description' => __('Store identity and application status will be edited here later.'),
        ],
    ];
    $page = $pages[request()->route()->getName()] ?? ['title' => __('Vendor'), 'columns' => [__('Item')], 'description' => ''];
@endphp

<x-vendor-layout :title="$page['title']">
    <x-slot name="header">{{ $page['title'] }}</x-slot>

    <x-ui.page-header :title="$page['title']" :description="$page['description']">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[['label' => __('Vendor'), 'href' => route('vendor.dashboard')], ['label' => $page['title']]]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    <x-ui.table :columns="$page['columns']">
        <x-slot:empty>
            <x-ui.empty-state :title="__('Not available yet')">
                {{ __('This vendor section is layout-only. No catalog or order logic is connected.') }}
            </x-ui.empty-state>
        </x-slot:empty>
    </x-ui.table>
</x-vendor-layout>
