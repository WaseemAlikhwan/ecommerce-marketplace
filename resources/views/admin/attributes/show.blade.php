<x-admin-layout :title="$attribute->name()">
    <x-ui.page-header :title="$attribute->name()" :description="__('Attribute values are global options vendors will select later. Deactivate instead of deleting.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Admin'), 'href' => route('admin.dashboard')],
                ['label' => __('Catalog'), 'href' => route('admin.catalog')],
                ['label' => __('Attributes'), 'href' => route('admin.attributes.index')],
                ['label' => $attribute->name()],
            ]" />
        </x-slot:breadcrumb>
        <x-slot:actions>
            <x-ui.button :href="route('admin.attribute-values.create', $attribute)" variant="primary">{{ __('Add value') }}</x-ui.button>
            <x-ui.button :href="route('admin.attributes.edit', $attribute)" variant="ghost">{{ __('Edit attribute') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    <div class="mb-6 flex flex-wrap items-center gap-3 border border-line bg-surface p-4">
        <div>
            <p class="text-caption text-ink-muted">{{ __('Code') }}</p>
            <p dir="ltr">{{ $attribute->code }}</p>
        </div>
        <div>
            <p class="text-caption text-ink-muted">{{ __('Position') }}</p>
            <p>{{ $attribute->position }}</p>
        </div>
        <div>
            <p class="text-caption text-ink-muted">{{ __('Status') }}</p>
            <x-ui.badge :tone="$attribute->is_active ? 'success' : 'neutral'">
                {{ $attribute->is_active ? __('Active') : __('Inactive') }}
            </x-ui.badge>
        </div>
        @if (! $attribute->is_active)
            <p class="text-caption text-ink-muted">{{ __('Inactive attributes and their values will be unavailable for future product selection.') }}</p>
        @endif
    </div>

    <form method="GET" action="{{ route('admin.attributes.show', $attribute) }}" class="mb-4 flex flex-wrap gap-2">
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
                    <th scope="col">{{ __('Position') }}</th>
                    <th scope="col">{{ __('Status') }}</th>
                    <th scope="col">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($values as $value)
                    <tr>
                        <td>
                            <a href="{{ route('admin.attribute-values.edit', [$attribute, $value]) }}" class="ds-link">{{ $value->name() }}</a>
                        </td>
                        <td dir="ltr" class="text-caption text-ink-muted">{{ $value->code }}</td>
                        <td>{{ $value->position }}</td>
                        <td>
                            <x-ui.badge :tone="$value->is_active ? 'success' : 'neutral'">
                                {{ $value->is_active ? __('Active') : __('Inactive') }}
                            </x-ui.badge>
                        </td>
                        <td>
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('admin.attribute-values.edit', [$attribute, $value]) }}" class="ds-link text-caption">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('admin.attribute-values.status', [$attribute, $value]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_active" value="{{ $value->is_active ? 0 : 1 }}">
                                    <button type="submit" class="ds-link text-caption">
                                        {{ $value->is_active ? __('Deactivate') : __('Activate') }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10">
                            <x-ui.empty-state
                                :title="__('No values yet')"
                                :action="__('Add value')"
                                :href="route('admin.attribute-values.create', $attribute)"
                            >
                                {{ __('Add values such as Red or Large. Product assignment comes in the next slice.') }}
                            </x-ui.empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($values->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $values->links() }}</div>
        @endif
    </div>
</x-admin-layout>
