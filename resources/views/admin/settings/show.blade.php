<x-admin-layout :title="__('Settings')">
    <x-ui.page-header :title="__('Settings')" :description="__('Read-only platform settings for staff. Vendor commission overrides stay out of V1.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Admin'), 'href' => route('admin.dashboard')],
                ['label' => __('Settings')],
            ]" />
        </x-slot:breadcrumb>
        <x-slot:actions>
            <x-ui.button :href="route('admin.users.index')" variant="ghost">{{ __('Users') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <section class="max-w-xl border border-line bg-surface p-6">
        <h2 class="font-display text-heading-3">{{ __('Global commission') }}</h2>
        <dl class="mt-4 space-y-3 text-sm">
            <div class="flex justify-between gap-3">
                <dt class="text-ink-muted">{{ __('Rate') }}</dt>
                <dd class="tabular-nums" dir="ltr">{{ $ratePercentLabel }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-ink-muted">{{ __('Basis points') }}</dt>
                <dd class="tabular-nums" dir="ltr">{{ $rateBps ?? '—' }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-ink-muted">{{ __('Updated') }}</dt>
                <dd>{{ $updatedAtLabel }}</dd>
            </div>
        </dl>
    </section>
</x-admin-layout>
