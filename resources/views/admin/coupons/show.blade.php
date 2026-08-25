<x-admin-layout :title="__('Coupon')">
    <x-ui.page-header :title="$coupon->code" :description="__('Staff coupon detail. Money values are integer minor units.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Admin'), 'href' => route('admin.dashboard')],
                ['label' => __('Coupons'), 'href' => route('admin.coupons.index')],
                ['label' => $coupon->code],
            ]" />
        </x-slot:breadcrumb>
        <x-slot:actions>
            <x-ui.button :href="route('admin.coupons.edit', $coupon)" variant="primary">{{ __('Edit') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    <div class="max-w-3xl space-y-6 border border-line bg-surface p-5">
        <dl class="grid gap-4 md:grid-cols-2">
            <div>
                <dt class="text-caption text-ink-muted">{{ __('Coupon code') }}</dt>
                <dd dir="ltr" class="mt-1 font-medium">{{ $coupon->code }}</dd>
            </div>
            <div>
                <dt class="text-caption text-ink-muted">{{ __('Status') }}</dt>
                <dd class="mt-1">
                    <x-ui.badge :tone="$coupon->is_active ? 'success' : 'neutral'">
                        {{ $coupon->is_active ? __('Active') : __('Inactive') }}
                    </x-ui.badge>
                </dd>
            </div>
            <div>
                <dt class="text-caption text-ink-muted">{{ __('Scope') }}</dt>
                <dd class="mt-1">
                    @if ($coupon->scope->value === 'vendor')
                        {{ __('Vendor') }}
                        @if ($coupon->vendor?->store)
                            — {{ $coupon->vendor->store->name }}
                        @endif
                    @else
                        {{ __('Platform') }}
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-caption text-ink-muted">{{ __('Discount type') }}</dt>
                <dd class="mt-1">
                    @if ($coupon->type->value === 'percent')
                        {{ __('Percent') }} · {{ $coupon->value }}%
                    @else
                        {{ __('Fixed amount') }} · <span dir="ltr">{{ $coupon->value }}</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-caption text-ink-muted">{{ __('Currency') }}</dt>
                <dd class="mt-1" dir="ltr">{{ $coupon->currency?->label() ?? $coupon->currency_code }}</dd>
            </div>
            <div>
                <dt class="text-caption text-ink-muted">{{ __('Schedule') }}</dt>
                <dd class="mt-1" dir="ltr">
                    {{ $coupon->starts_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                    →
                    {{ $coupon->ends_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-caption text-ink-muted">{{ __('Minimum eligible amount (minor units)') }}</dt>
                <dd class="mt-1" dir="ltr">{{ $coupon->min_eligible_amount_minor }}</dd>
            </div>
            <div>
                <dt class="text-caption text-ink-muted">{{ __('Maximum discount (minor units)') }}</dt>
                <dd class="mt-1" dir="ltr">{{ $coupon->max_discount_amount_minor ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-caption text-ink-muted">{{ __('Global usage limit') }}</dt>
                <dd class="mt-1" dir="ltr">{{ $coupon->global_usage_limit ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-caption text-ink-muted">{{ __('Per-user usage limit') }}</dt>
                <dd class="mt-1" dir="ltr">{{ $coupon->per_user_usage_limit ?? '—' }}</dd>
            </div>
        </dl>

        <div>
            <h2 class="text-sm font-medium">{{ __('Restricted categories') }}</h2>
            @if ($coupon->categories->isEmpty())
                <p class="mt-2 text-caption text-ink-muted">{{ __('None') }}</p>
            @else
                <ul class="mt-2 list-inside list-disc text-sm">
                    @foreach ($coupon->categories as $category)
                        <li>{{ $category->name() }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div>
            <h2 class="text-sm font-medium">{{ __('Restricted products') }}</h2>
            @if ($coupon->products->isEmpty())
                <p class="mt-2 text-caption text-ink-muted">{{ __('None') }}</p>
            @else
                <ul class="mt-2 list-inside list-disc text-sm">
                    @foreach ($coupon->products as $product)
                        <li>#{{ $product->id }} — {{ $product->name() }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-admin-layout>
