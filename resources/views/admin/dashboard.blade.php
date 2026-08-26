<x-admin-layout :title="__('Admin')">
    <x-ui.page-header
        :title="__('Operations desk')"
        :description="__('Staff KPIs for applications, reviews, orders, payments, catalog, and vendors.')"
    >
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[['label' => __('Admin')], ['label' => __('Overview')]]" />
        </x-slot:breadcrumb>
    </x-ui.page-header>

    <div class="mb-6 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('admin.vendors') }}" class="border border-line bg-surface px-3 py-1.5 text-ink transition hover:border-ink/25">{{ __('Vendors') }}</a>
        <a href="{{ route('admin.reviews.index') }}" class="border border-line bg-surface px-3 py-1.5 text-ink transition hover:border-ink/25">{{ __('Product reviews') }}</a>
        <a href="{{ route('admin.coupons.index') }}" class="border border-line bg-surface px-3 py-1.5 text-ink transition hover:border-ink/25">{{ __('Coupons') }}</a>
        <a href="{{ route('admin.catalog') }}" class="border border-line bg-surface px-3 py-1.5 text-ink transition hover:border-ink/25">{{ __('Catalog') }}</a>
        <a href="{{ route('admin.orders') }}" class="border border-line bg-surface px-3 py-1.5 text-ink transition hover:border-ink/25">{{ __('Orders') }}</a>
        <a href="{{ route('admin.vendor-orders.index') }}" class="border border-line bg-surface px-3 py-1.5 text-ink transition hover:border-ink/25">{{ __('Vendor orders') }}</a>
        <a href="{{ route('admin.payments.index') }}" class="border border-line bg-surface px-3 py-1.5 text-ink transition hover:border-ink/25">{{ __('Payments') }}</a>
        <a href="{{ route('admin.users.index') }}" class="border border-line bg-surface px-3 py-1.5 text-ink transition hover:border-ink/25">{{ __('Users') }}</a>
        <a href="{{ route('admin.settings') }}" class="border border-line bg-surface px-3 py-1.5 text-ink transition hover:border-ink/25">{{ __('Settings') }}</a>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <a href="{{ route('admin.vendors') }}" class="border border-line bg-surface px-5 py-4 transition hover:border-ink/25">
            <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Pending applications') }}</p>
            <p class="mt-2 font-display text-heading-2">{{ $stats->pendingVendorApplications }}</p>
        </a>
        <a href="{{ route('admin.reviews.index') }}" class="border border-line bg-surface px-5 py-4 transition hover:border-ink/25">
            <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Pending product reviews') }}</p>
            <p class="mt-2 font-display text-heading-2">{{ $stats->pendingProductReviews }}</p>
        </a>
        <a href="{{ route('admin.orders') }}" class="border border-line bg-surface px-5 py-4 transition hover:border-ink/25">
            <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Placed parent orders') }}</p>
            <p class="mt-2 font-display text-heading-2">{{ $stats->placedParentOrders }}</p>
        </a>
        <a href="{{ route('admin.catalog') }}" class="border border-line bg-surface px-5 py-4 transition hover:border-ink/25">
            <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Published products') }}</p>
            <p class="mt-2 font-display text-heading-2">{{ $stats->publishedProducts }}</p>
        </a>
        <a href="{{ route('admin.vendors') }}" class="border border-line bg-surface px-5 py-4 transition hover:border-ink/25">
            <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Approved vendors') }}</p>
            <p class="mt-2 font-display text-heading-2">{{ $stats->approvedVendors }}</p>
        </a>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="border border-line bg-surface px-5 py-4">
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="font-display text-heading-3">{{ __('Vendor orders by status') }}</h2>
                <a href="{{ route('admin.vendor-orders.index') }}" class="text-caption text-ink-muted underline-offset-2 hover:underline">{{ __('Vendor orders') }}</a>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ($stats->vendorOrdersByStatus as $status => $count)
                    <div>
                        <dt class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __(ucfirst($status)) }}</dt>
                        <dd class="mt-1 font-display text-heading-3">{{ $count }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="border border-line bg-surface px-5 py-4">
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="font-display text-heading-3">{{ __('COD payments by status') }}</h2>
                <a href="{{ route('admin.payments.index') }}" class="text-caption text-ink-muted underline-offset-2 hover:underline">{{ __('Payments') }}</a>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ($stats->codPaymentsByStatus as $status => $count)
                    <div>
                        <dt class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __(ucfirst($status)) }}</dt>
                        <dd class="mt-1 font-display text-heading-3">{{ $count }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>

    <section class="mt-6 border border-line bg-surface px-5 py-4">
        <h2 class="font-display text-heading-3">{{ __('Recognized commission') }}</h2>
        @if ($recognizedCommissionLabels === [])
            <p class="mt-3 text-caption text-ink-muted">{{ __('No recognized commission yet.') }}</p>
        @else
            <ul class="mt-4 space-y-2">
                @foreach ($recognizedCommissionLabels as $code => $label)
                    <li class="flex items-baseline justify-between gap-3 border-b border-line py-2 last:border-b-0">
                        <span class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ $code }}</span>
                        <span class="font-display text-heading-3 tabular-nums" dir="ltr">{{ $label }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-admin-layout>
