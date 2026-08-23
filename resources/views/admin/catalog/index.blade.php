<x-admin-layout :title="__('Catalog')">
    <x-ui.page-header :title="__('Catalog taxonomy')" :description="__('Manage global categories, brands, and attributes. Product assignment comes later.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[['label' => __('Admin'), 'href' => route('admin.dashboard')], ['label' => __('Catalog')]]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div class="border border-line bg-surface p-5">
            <p class="text-[11px] uppercase tracking-[0.16em] text-ink-muted">{{ __('Categories') }}</p>
            <p class="mt-2 font-display text-heading-2">{{ $categoryCount }}</p>
            <p class="mt-1 text-caption text-ink-muted">{{ __(':count active', ['count' => $activeCategoryCount]) }}</p>
            <div class="mt-5 flex flex-wrap gap-2">
                <x-ui.button :href="route('admin.categories.index')" variant="primary">{{ __('Manage categories') }}</x-ui.button>
                <x-ui.button :href="route('admin.categories.create')" variant="ghost">{{ __('Add category') }}</x-ui.button>
            </div>
        </div>
        <div class="border border-line bg-surface p-5">
            <p class="text-[11px] uppercase tracking-[0.16em] text-ink-muted">{{ __('Brands') }}</p>
            <p class="mt-2 font-display text-heading-2">{{ $brandCount }}</p>
            <p class="mt-1 text-caption text-ink-muted">{{ __(':count active', ['count' => $activeBrandCount]) }}</p>
            <div class="mt-5 flex flex-wrap gap-2">
                <x-ui.button :href="route('admin.brands.index')" variant="primary">{{ __('Manage brands') }}</x-ui.button>
                <x-ui.button :href="route('admin.brands.create')" variant="ghost">{{ __('Add brand') }}</x-ui.button>
            </div>
        </div>
        <div class="border border-line bg-surface p-5">
            <p class="text-[11px] uppercase tracking-[0.16em] text-ink-muted">{{ __('Attributes') }}</p>
            <p class="mt-2 font-display text-heading-2">{{ $attributeCount }}</p>
            <p class="mt-1 text-caption text-ink-muted">{{ __(':count active', ['count' => $activeAttributeCount]) }}</p>
            <div class="mt-5 flex flex-wrap gap-2">
                <x-ui.button :href="route('admin.attributes.index')" variant="primary">{{ __('Manage attributes') }}</x-ui.button>
                <x-ui.button :href="route('admin.attributes.create')" variant="ghost">{{ __('Add attribute') }}</x-ui.button>
            </div>
        </div>
    </div>
</x-admin-layout>
