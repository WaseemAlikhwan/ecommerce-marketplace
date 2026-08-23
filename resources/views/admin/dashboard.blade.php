<x-admin-layout :title="__('Admin')">
    <x-ui.page-header :title="__('Operations desk')" :description="__('Review vendor applications. Catalog and order tools remain for later phases.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[['label' => __('Admin')], ['label' => __('Overview')]]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    <div class="grid gap-3 md:grid-cols-3">
        <a href="{{ route('admin.vendors') }}" class="border border-line bg-surface px-5 py-4 transition hover:border-ink/25">
            <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Applications') }}</p>
            <p class="mt-2 font-display text-heading-2">{{ $pendingApplications }}</p>
            <p class="mt-1 text-caption text-ink-muted">{{ __('Pending') }}</p>
        </a>
        <div class="border border-line bg-surface px-5 py-4">
            <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Vendors') }}</p>
            <p class="mt-2 font-display text-heading-2">{{ $approvedVendors }}</p>
            <p class="mt-1 text-caption text-ink-muted">{{ __('Approved') }}</p>
        </div>
        <div class="border border-line bg-surface px-5 py-4">
            <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Catalog') }}</p>
            <p class="mt-2 font-display text-heading-2">{{ __('Later') }}</p>
        </div>
    </div>
</x-admin-layout>
