<x-admin-layout :title="__('Vendor application')">
    <x-ui.page-header :title="$application->store_name" :description="__('Review the application, then approve or reject it.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Admin'), 'href' => route('admin.dashboard')],
                ['label' => __('Vendors'), 'href' => route('admin.vendors')],
                ['label' => $application->store_name],
            ]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    <x-input-error class="mb-4" :messages="$errors->get('application')" />

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="space-y-4 border border-line bg-surface p-5 lg:col-span-7">
            <div class="flex items-center justify-between gap-3">
                <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Application') }}</p>
                <x-ui.badge :tone="$application->isPending() ? 'warning' : ($application->status->value === 'approved' ? 'success' : 'danger')">
                    {{ __($application->status->value === 'pending' ? 'Pending' : ($application->status->value === 'approved' ? 'Approved' : 'Rejected')) }}
                </x-ui.badge>
            </div>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-ink-muted">{{ __('Store name') }}</dt>
                    <dd class="mt-0.5">{{ $application->store_name }}</dd>
                </div>
                <div>
                    <dt class="text-ink-muted">{{ __('Owner') }}</dt>
                    <dd class="mt-0.5">{{ $application->user->name }} · {{ $application->user->email }} · {{ $application->user->phone }}</dd>
                </div>
                @if ($application->note)
                    <div>
                        <dt class="text-ink-muted">{{ __('Note to reviewers') }}</dt>
                        <dd class="mt-0.5">{{ $application->note }}</dd>
                    </div>
                @endif
                @if ($application->reviewer)
                    <div>
                        <dt class="text-ink-muted">{{ __('Reviewed by') }}</dt>
                        <dd class="mt-0.5">{{ $application->reviewer->name }} · {{ $application->reviewed_at?->format('Y-m-d H:i') }}</dd>
                    </div>
                @endif
                @if ($application->rejection_reason)
                    <div>
                        <dt class="text-ink-muted">{{ __('Reason') }}</dt>
                        <dd class="mt-0.5">{{ $application->rejection_reason }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        @if ($application->isPending())
            <div class="space-y-4 lg:col-span-5">
                <form method="POST" action="{{ route('admin.vendor-applications.approve', $application) }}" class="border border-line bg-surface p-5">
                    @csrf
                    <p class="text-sm text-ink-muted">{{ __('Approval grants vendor access and creates exactly one store.') }}</p>
                    <x-primary-button class="mt-4">{{ __('Approve application') }}</x-primary-button>
                </form>
                <form method="POST" action="{{ route('admin.vendor-applications.reject', $application) }}" class="border border-line bg-surface p-5">
                    @csrf
                    <div>
                        <x-input-label for="rejection_reason" :value="__('Rejection reason')" />
                        <textarea id="rejection_reason" name="rejection_reason" rows="4" class="ds-input">{{ old('rejection_reason') }}</textarea>
                        <x-input-error :messages="$errors->get('rejection_reason')" />
                    </div>
                    <x-secondary-button class="mt-4">{{ __('Reject application') }}</x-secondary-button>
                </form>
            </div>
        @endif
    </div>
</x-admin-layout>
