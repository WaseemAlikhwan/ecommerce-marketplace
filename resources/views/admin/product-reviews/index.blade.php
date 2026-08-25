<x-admin-layout :title="__('Product reviews')">
    <x-ui.page-header :title="__('Pending reviews')" :description="__('Moderate customer product reviews before they appear on the storefront.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[['label' => __('Admin'), 'href' => route('admin.dashboard')], ['label' => __('Product reviews')]]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    <form method="GET" action="{{ route('admin.reviews.index') }}" class="mb-4 flex flex-wrap gap-2">
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
                    <th scope="col">{{ __('Product') }}</th>
                    <th scope="col">{{ __('Customer') }}</th>
                    <th scope="col">{{ __('Rating') }}</th>
                    <th scope="col">{{ __('Status') }}</th>
                    <th scope="col">{{ __('Updated') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reviews as $review)
                    <tr>
                        <td>
                            <a href="{{ route('admin.reviews.show', $review) }}" class="ds-link">{{ $review->product->name() }}</a>
                        </td>
                        <td>{{ $review->user->name }}</td>
                        <td>{{ $review->rating }}</td>
                        <td>{{ __($review->status->value === 'pending' ? 'Pending' : ($review->status->value === 'approved' ? 'Approved' : 'Rejected')) }}</td>
                        <td>{{ $review->updated_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10">
                            <x-ui.empty-state :title="__('No reviews')">
                                {{ __('No product reviews match this filter.') }}
                            </x-ui.empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($reviews->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $reviews->links() }}</div>
        @endif
    </div>
</x-admin-layout>
