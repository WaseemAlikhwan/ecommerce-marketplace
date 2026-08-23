<x-vendor-layout :title="__('Vendor')">
    <x-slot name="header">{{ __('Overview') }}</x-slot>

    <x-ui.page-header :title="__('Seller workspace')" :description="__('Your store is live for identity setup. Catalog and orders arrive in later phases.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[['label' => __('Vendor')], ['label' => __('Overview')]]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    <div class="grid gap-3 md:grid-cols-3">
        <div class="border border-line bg-surface px-5 py-4">
            <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Store') }}</p>
            <p class="mt-2 font-display text-heading-3">{{ $store?->name ?? __('Your store') }}</p>
        </div>
        <div class="border border-line bg-surface px-5 py-4">
            <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Store status') }}</p>
            <p class="mt-2 font-display text-heading-3">{{ $store?->status?->value === 'active' ? __('Active') : __('Suspended') }}</p>
        </div>
        <div class="border border-line bg-surface px-5 py-4">
            <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Vendor') }}</p>
            <p class="mt-2 font-display text-heading-3">{{ $vendor->status->value === 'approved' ? __('Approved') : __('Suspended') }}</p>
        </div>
    </div>

    <div class="mt-6">
        <x-ui.button :href="route('vendor.store')" variant="secondary" type="button">{{ __('Edit store profile') }}</x-ui.button>
    </div>
</x-vendor-layout>
