<x-admin-layout :title="__('Categories')">
    <x-ui.page-header :title="__('Categories')" :description="__('Adjacency-list taxonomy with up to three levels. Deactivate instead of deleting.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Admin'), 'href' => route('admin.dashboard')],
                ['label' => __('Catalog'), 'href' => route('admin.catalog')],
                ['label' => __('Categories')],
            ]" />
        </x-slot:breadcrumb>
        <x-slot:actions>
            <x-ui.button :href="route('admin.categories.create')" variant="primary">{{ __('Add category') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="GET" action="{{ route('admin.categories.index') }}" class="mb-4 flex flex-wrap gap-2">
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
                    <th scope="col">{{ __('Depth') }}</th>
                    <th scope="col">{{ __('Parent') }}</th>
                    <th scope="col">{{ __('Status') }}</th>
                    <th scope="col">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    @php($depth = $category->depth())
                    <tr>
                        <td>
                            <span class="inline-block" style="padding-inline-start: {{ ($depth - 1) * 1.1 }}rem">
                                <a href="{{ route('admin.categories.edit', $category) }}" class="ds-link">{{ $category->name() }}</a>
                            </span>
                        </td>
                        <td dir="ltr" class="text-caption text-ink-muted">{{ $category->slug }}</td>
                        <td>
                            <x-ui.badge>
                                {{ $depth === 1 ? __('Root') : ($depth === 2 ? __('Subcategory') : __('Leaf')) }}
                                · {{ $depth }}/{{ \App\Models\Category::MAX_DEPTH }}
                            </x-ui.badge>
                        </td>
                        <td>{{ $category->parent?->name() ?? '—' }}</td>
                        <td>
                            <x-ui.badge :tone="$category->is_active ? 'success' : 'neutral'">
                                {{ $category->is_active ? __('Active') : __('Inactive') }}
                            </x-ui.badge>
                        </td>
                        <td>
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('admin.categories.edit', $category) }}" class="ds-link text-caption">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('admin.categories.status', $category) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_active" value="{{ $category->is_active ? 0 : 1 }}">
                                    <button type="submit" class="ds-link text-caption">
                                        {{ $category->is_active ? __('Deactivate') : __('Activate') }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10">
                            <x-ui.empty-state :title="__('No categories yet')">
                                {{ __('Create the first root category to begin the taxonomy.') }}
                            </x-ui.empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($categories->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $categories->links() }}</div>
        @endif
    </div>
</x-admin-layout>
