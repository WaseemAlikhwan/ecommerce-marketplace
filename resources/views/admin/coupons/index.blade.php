<x-admin-layout :title="__('Coupons')">
    <x-ui.page-header :title="__('Coupons')" :description="__('Staff-managed platform and vendor-scoped coupons. Deactivate instead of deleting.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Admin'), 'href' => route('admin.dashboard')],
                ['label' => __('Coupons')],
            ]" />
        </x-slot:breadcrumb>
        <x-slot:actions>
            <x-ui.button :href="route('admin.coupons.create')" variant="primary">{{ __('Add coupon') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="GET" action="{{ route('admin.coupons.index') }}" class="mb-4 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ $q }}" class="ds-input max-w-xs py-2" placeholder="{{ __('Search by code…') }}" dir="ltr">
        <x-ui.select name="scope" class="w-44 py-2 text-sm" onchange="this.form.submit()">
            <option value="">{{ __('All scopes') }}</option>
            <option value="platform" @selected($scope === 'platform')>{{ __('Platform') }}</option>
            <option value="vendor" @selected($scope === 'vendor')>{{ __('Vendor') }}</option>
        </x-ui.select>
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
                    <th scope="col">{{ __('Code') }}</th>
                    <th scope="col">{{ __('Scope') }}</th>
                    <th scope="col">{{ __('Type') }}</th>
                    <th scope="col">{{ __('Currency') }}</th>
                    <th scope="col">{{ __('Status') }}</th>
                    <th scope="col">{{ __('Updated') }}</th>
                    <th scope="col">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($coupons as $coupon)
                    <tr>
                        <td>
                            <a href="{{ route('admin.coupons.show', $coupon) }}" class="ds-link" dir="ltr">{{ $coupon->code }}</a>
                        </td>
                        <td>
                            @if ($coupon->scope->value === 'vendor')
                                {{ __('Vendor') }}
                                @if ($coupon->vendor?->store)
                                    <span class="text-caption text-ink-muted">({{ $coupon->vendor->store->name }})</span>
                                @endif
                            @else
                                {{ __('Platform') }}
                            @endif
                        </td>
                        <td>
                            @if ($coupon->type->value === 'percent')
                                {{ __('Percent') }} · {{ $coupon->value }}%
                            @else
                                {{ __('Fixed') }} · <span dir="ltr">{{ $coupon->value }}</span>
                            @endif
                        </td>
                        <td dir="ltr">{{ $coupon->currency_code }}</td>
                        <td>
                            <x-ui.badge :tone="$coupon->is_active ? 'success' : 'neutral'">
                                {{ $coupon->is_active ? __('Active') : __('Inactive') }}
                            </x-ui.badge>
                        </td>
                        <td>{{ $coupon->updated_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                        <td>
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('admin.coupons.show', $coupon) }}" class="ds-link text-caption">{{ __('View') }}</a>
                                <a href="{{ route('admin.coupons.edit', $coupon) }}" class="ds-link text-caption">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('admin.coupons.status', $coupon) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_active" value="{{ $coupon->is_active ? 0 : 1 }}">
                                    <button type="submit" class="ds-link text-caption">
                                        {{ $coupon->is_active ? __('Deactivate') : __('Activate') }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10">
                            <x-ui.empty-state :title="__('No coupons yet')">
                                {{ __('Create the first platform or vendor-scoped coupon.') }}
                            </x-ui.empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($coupons->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $coupons->links() }}</div>
        @endif
    </div>
</x-admin-layout>
