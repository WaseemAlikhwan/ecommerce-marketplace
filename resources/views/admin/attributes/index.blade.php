<x-admin-layout :title="__('Attributes')">
    <x-ui.page-header :title="__('Attributes')" :description="__('Global attribute dictionary for later variable products. Deactivate instead of deleting.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Admin'), 'href' => route('admin.dashboard')],
                ['label' => __('Catalog'), 'href' => route('admin.catalog')],
                ['label' => __('Attributes')],
            ]" />
        </x-slot:breadcrumb>
        <x-slot:actions>
            <x-ui.button :href="route('admin.attributes.create')" variant="primary">{{ __('Add attribute') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="GET" action="{{ route('admin.attributes.index') }}" class="mb-4 flex flex-wrap gap-2">
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
                    <th scope="col">{{ __('Code') }}</th>
                    <th scope="col">{{ __('Values') }}</th>
                    <th scope="col">{{ __('Position') }}</th>
                    <th scope="col">{{ __('Status') }}</th>
                    <th scope="col">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($attributes as $attribute)
                    <tr>
                        <td>
                            <a href="{{ route('admin.attributes.show', $attribute) }}" class="ds-link">{{ $attribute->name() }}</a>
                        </td>
                        <td dir="ltr" class="text-caption text-ink-muted">{{ $attribute->code }}</td>
                        <td>{{ $attribute->values_count }}</td>
                        <td>{{ $attribute->position }}</td>
                        <td>
                            <x-ui.badge :tone="$attribute->is_active ? 'success' : 'neutral'">
                                {{ $attribute->is_active ? __('Active') : __('Inactive') }}
                            </x-ui.badge>
                        </td>
                        <td>
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('admin.attributes.show', $attribute) }}" class="ds-link text-caption">{{ __('Values') }}</a>
                                <a href="{{ route('admin.attributes.edit', $attribute) }}" class="ds-link text-caption">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('admin.attributes.status', $attribute) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_active" value="{{ $attribute->is_active ? 0 : 1 }}">
                                    <button type="submit" class="ds-link text-caption">
                                        {{ $attribute->is_active ? __('Deactivate') : __('Activate') }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10">
                            <x-ui.empty-state
                                :title="__('No attributes yet')"
                                :action="__('Add attribute')"
                                :href="route('admin.attributes.create')"
                            >
                                {{ __('Create the first global attribute. Vendors will select values later.') }}
                            </x-ui.empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($attributes->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $attributes->links() }}</div>
        @endif
    </div>
</x-admin-layout>
