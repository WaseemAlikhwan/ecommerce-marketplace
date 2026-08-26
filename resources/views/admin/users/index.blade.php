<x-admin-layout :title="__('Users')">
    <x-ui.page-header :title="__('Users')" :description="__('Read-only account overview with role badges. Permission assignment stays out of V1.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Admin'), 'href' => route('admin.dashboard')],
                ['label' => __('Users')],
            ]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    <div class="ds-table-wrap">
        <table class="ds-table">
            <thead>
                <tr>
                    <th scope="col">{{ __('Name') }}</th>
                    <th scope="col">{{ __('Email') }}</th>
                    <th scope="col">{{ __('Roles') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td dir="ltr">{{ $row['email'] }}</td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($row['roles'] as $role)
                                    @php
                                        $roleLabel = match ($role) {
                                            'customer' => __('Customer'),
                                            'vendor' => __('Vendor'),
                                            'admin' => __('Admin'),
                                            'super_admin' => __('Super admin'),
                                            default => $role,
                                        };
                                    @endphp
                                    <span class="border border-line px-2 py-0.5 text-[11px] uppercase tracking-[0.12em] text-ink-muted">{{ $roleLabel }}</span>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-10">
                            <x-ui.empty-state :title="__('No users')">
                                {{ __('No users yet.') }}
                            </x-ui.empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($users->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $users->links() }}</div>
        @endif
    </div>
</x-admin-layout>
