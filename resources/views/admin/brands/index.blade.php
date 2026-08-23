<x-admin-layout :title="__('Brands')">
    <x-ui.page-header :title="__('Brands')" :description="__('Global brands managed by platform staff. Deactivate instead of deleting.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Admin'), 'href' => route('admin.dashboard')],
                ['label' => __('Catalog'), 'href' => route('admin.catalog')],
                ['label' => __('Brands')],
            ]" />
        </x-slot:breadcrumb>
        <x-slot:actions>
            <x-ui.button :href="route('admin.brands.create')" variant="primary">{{ __('Add brand') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="GET" action="{{ route('admin.brands.index') }}" class="mb-4 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ $q }}" class="ds-input max-w-xs py-2" placeholder="{{ __('Search by name…') }}">
        <x-ui.select name="status" class="w-44 py-2 text-sm" onchange="this.form.submit()">
            <option value="">{{ __('All statuses') }}</option>
            <option value="active" @selected($status === 'active')>{{ __('Active') }}</option>
            <option value="inactive" @selected($status === 'inactive')>{{ __('Inactive') }}</option>
        </x-ui.select>
        <x-ui.button type="submit" variant="ghost">{{ __('Filter') }}</x-ui.button>
    </form>

    <div class="ds-table-wrap">
        <table class="ds-table">
            <thead>
                <tr>
                    <th scope="col">{{ __('Name') }}</th>
                    <th scope="col">{{ __('Slug') }}</th>
                    <th scope="col">{{ __('Status') }}</th>
                    <th scope="col">{{ __('Updated') }}</th>
                    <th scope="col">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($brands as $brand)
                    <tr>
                        <td>
                            <a href="{{ route('admin.brands.edit', $brand) }}" class="ds-link">{{ $brand->name() }}</a>
                        </td>
                        <td dir="ltr" class="text-caption text-ink-muted">{{ $brand->slug }}</td>
                        <td>
                            <x-ui.badge :tone="$brand->is_active ? 'success' : 'neutral'">
                                {{ $brand->is_active ? __('Active') : __('Inactive') }}
                            </x-ui.badge>
                        </td>
                        <td>{{ $brand->updated_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                        <td>
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('admin.brands.edit', $brand) }}" class="ds-link text-caption">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('admin.brands.status', $brand) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_active" value="{{ $brand->is_active ? 0 : 1 }}">
                                    <button type="submit" class="ds-link text-caption">
                                        {{ $brand->is_active ? __('Deactivate') : __('Activate') }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10">
                            <x-ui.empty-state :title="__('No brands yet')">
                                {{ __('Create the first brand for vendors to select later.') }}
                            </x-ui.empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($brands->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $brands->links() }}</div>
        @endif
    </div>
</x-admin-layout>
