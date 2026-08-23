<x-admin-layout :title="__('Vendors')">
    <x-ui.page-header :title="__('Vendor applications')" :description="__('Review pending applications. Approval creates one store for the seller.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[['label' => __('Admin'), 'href' => route('admin.dashboard')], ['label' => __('Vendors')]]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    <form method="GET" action="{{ route('admin.vendors') }}" class="mb-4 flex flex-wrap gap-2">
        <x-ui.select name="status" class="w-44 py-2 text-sm" onchange="this.form.submit()">
            <option value="pending" @selected($status === 'pending')>{{ __('Pending') }}</option>
            <option value="approved" @selected($status === 'approved')>{{ __('Approved') }}</option>
            <option value="rejected" @selected($status === 'rejected')>{{ __('Rejected') }}</option>
        </x-ui.select>
    </form>

    <div class="ds-table-wrap">
        <table class="ds-table">
            <thead>
                <tr>
                    <th scope="col">{{ __('Store') }}</th>
                    <th scope="col">{{ __('Owner') }}</th>
                    <th scope="col">{{ __('Status') }}</th>
                    <th scope="col">{{ __('Updated') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($applications as $application)
                    <tr>
                        <td>
                            <a href="{{ route('admin.vendor-applications.show', $application) }}" class="ds-link">{{ $application->store_name }}</a>
                        </td>
                        <td>{{ $application->user->name }}</td>
                        <td>{{ __($application->status->value === 'pending' ? 'Pending' : ($application->status->value === 'approved' ? 'Approved' : 'Rejected')) }}</td>
                        <td>{{ $application->updated_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10">
                            <x-ui.empty-state :title="__('No applications')">
                                {{ __('No vendor applications match this filter.') }}
                            </x-ui.empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($applications->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $applications->links() }}</div>
        @endif
    </div>
</x-admin-layout>
