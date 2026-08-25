<x-admin-layout :title="__('Product reviews')">
    <x-ui.page-header :title="$review->product->name()" :description="__('Review the content, then approve or reject it.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Admin'), 'href' => route('admin.dashboard')],
                ['label' => __('Product reviews'), 'href' => route('admin.reviews.index')],
                ['label' => $review->product->name()],
            ]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    <x-input-error class="mb-4" :messages="$errors->get('review')" />

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="space-y-4 border border-line bg-surface p-5 lg:col-span-7">
            <div class="flex items-center justify-between gap-3">
                <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Reviews') }}</p>
                <x-ui.badge :tone="$review->status->value === 'pending' ? 'warning' : ($review->status->value === 'approved' ? 'success' : 'danger')">
                    {{ __($review->status->value === 'pending' ? 'Pending' : ($review->status->value === 'approved' ? 'Approved' : 'Rejected')) }}
                </x-ui.badge>
            </div>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-ink-muted">{{ __('Product') }}</dt>
                    <dd class="mt-0.5">{{ $review->product->name() }}</dd>
                </div>
                <div>
                    <dt class="text-ink-muted">{{ __('Customer') }}</dt>
                    <dd class="mt-0.5">{{ $review->user->name }} · {{ $review->user->email }}</dd>
                </div>
                <div>
                    <dt class="text-ink-muted">{{ __('Rating') }}</dt>
                    <dd class="mt-0.5">{{ $review->rating }}</dd>
                </div>
                <div>
                    <dt class="text-ink-muted">{{ __('Review body') }}</dt>
                    <dd class="mt-0.5">{{ $review->body ?: '—' }}</dd>
                </div>
            </dl>
        </div>

        @if ($review->status->value === 'pending')
            <div class="space-y-4 lg:col-span-5">
                <form method="POST" action="{{ route('admin.reviews.approve', $review) }}" class="border border-line bg-surface p-5">
                    @csrf
                    <p class="text-sm text-ink-muted">{{ __('Moderate customer product reviews before they appear on the storefront.') }}</p>
                    <x-primary-button class="mt-4">{{ __('Approve review') }}</x-primary-button>
                </form>
                <form method="POST" action="{{ route('admin.reviews.reject', $review) }}" class="border border-line bg-surface p-5">
                    @csrf
                    <x-secondary-button>{{ __('Reject review') }}</x-secondary-button>
                </form>
            </div>
        @endif
    </div>
</x-admin-layout>
