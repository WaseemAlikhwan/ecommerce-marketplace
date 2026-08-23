<x-app-layout :title="__('Become a vendor')">
    <x-ui.page-header :title="__('Become a vendor')" :description="__('Apply with a verified email. One store is created when an admin approves your application.')" />

    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    @if (! auth()->user()->hasVerifiedEmail())
        <x-ui.alert tone="warning" class="mb-6" :title="__('Your email address is unverified.')">
            <p>{{ __('Verify your email before applying as a vendor.') }}</p>
        </x-ui.alert>
    @endif

    @if ($application)
        <div class="mb-8 border border-line bg-surface p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Latest application') }}</p>
                    <p class="mt-1 font-display text-heading-3">{{ $application->store_name }}</p>
                </div>
                <x-ui.badge :tone="$application->isPending() ? 'warning' : ($application->status->value === 'approved' ? 'success' : 'danger')">
                    {{ __($application->status->value === 'pending' ? 'Pending' : ($application->status->value === 'approved' ? 'Approved' : 'Rejected')) }}
                </x-ui.badge>
            </div>
            @if ($application->note)
                <p class="mt-3 text-sm text-ink-muted">{{ $application->note }}</p>
            @endif
            @if ($application->status->value === 'rejected' && $application->rejection_reason)
                <p class="mt-3 text-sm">{{ __('Reason') }}: {{ $application->rejection_reason }}</p>
            @endif
            @if ($application->status->value === 'approved')
                <div class="mt-4">
                    <x-ui.button :href="route('vendor.dashboard')" type="button">{{ __('Open seller workspace') }}</x-ui.button>
                </div>
            @endif
        </div>
    @endif

    @if ($canApply)
        <form method="POST" action="{{ route('account.vendor-application.store') }}" class="max-w-xl space-y-5 border border-line bg-surface p-5">
            @csrf
            <div>
                <x-input-label for="store_name" :value="__('Store name')" />
                <x-text-input id="store_name" name="store_name" type="text" :value="old('store_name')" required />
                <x-input-error :messages="$errors->get('store_name')" />
            </div>
            <div>
                <x-input-label for="note" :value="__('Note to reviewers')" />
                <textarea id="note" name="note" rows="4" class="ds-input">{{ old('note') }}</textarea>
                <x-input-error :messages="$errors->get('note')" />
            </div>
            <x-primary-button>{{ __('Submit application') }}</x-primary-button>
        </form>
    @elseif (auth()->user()->isVendor())
        <p class="text-sm text-ink-muted">{{ __('You already have a vendor account.') }}</p>
    @elseif ($application?->isPending())
        <p class="text-sm text-ink-muted">{{ __('You already have a pending vendor application.') }}</p>
    @endif
</x-app-layout>
