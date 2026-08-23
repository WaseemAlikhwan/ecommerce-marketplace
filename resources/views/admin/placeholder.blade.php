@php
    $pages = [
        'admin.vendors' => [
            'title' => __('Vendors'),
            'columns' => [__('Store'), __('Owner'), __('Status'), __('Updated')],
            'description' => __('Vendor applications and store status will be managed here later.'),
        ],
        'admin.catalog' => [
            'title' => __('Catalog'),
            'columns' => [__('Product'), __('Store'), __('Status'), __('Updated')],
            'description' => __('Category and product moderation will use this workspace later.'),
        ],
        'admin.orders' => [
            'title' => __('Orders'),
            'columns' => [__('Order'), __('Customer'), __('Status'), __('Total')],
            'description' => __('Parent and vendor-order operations will appear here later.'),
        ],
        'admin.settings' => [
            'title' => __('Settings'),
            'columns' => [__('Key'), __('Value'), __('Scope')],
            'description' => __('Platform settings stay out of scope until later phases.'),
        ],
    ];
    $page = $pages[request()->route()->getName()] ?? ['title' => __('Admin'), 'columns' => [__('Item')], 'description' => ''];
@endphp

<x-admin-layout :title="$page['title']">
    <x-ui.page-header :title="$page['title']" :description="$page['description']">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[['label' => __('Admin'), 'href' => route('admin.dashboard')], ['label' => $page['title']]]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap gap-2">
        <x-ui.select class="w-44 py-2 text-sm" disabled>
            <option>{{ __('All statuses') }}</option>
            <option>{{ __('Pending') }}</option>
            <option>{{ __('Approved') }}</option>
        </x-ui.select>
        <input type="search" class="ds-input max-w-xs py-2" disabled placeholder="{{ __('Filter…') }}">
    </div>

    <x-ui.table :columns="$page['columns']">
        <x-slot:empty>
            <x-ui.empty-state :title="__('Not available yet')">
                {{ __('This admin section is layout-only. No commerce or operations logic is connected.') }}
            </x-ui.empty-state>
        </x-slot:empty>
    </x-ui.table>
</x-admin-layout>
