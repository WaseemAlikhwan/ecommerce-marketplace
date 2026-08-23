<x-app-layout :title="__('Profile')">
    <x-ui.page-header :title="__('Account settings')" :description="__('Update your name, contact details, and password.')" />

    <div class="grid gap-8 lg:grid-cols-12">
        <div class="space-y-8 lg:col-span-8">
            <section class="border border-line bg-surface p-6 sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </section>
            <section class="border border-line bg-surface p-6 sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </section>
            <section class="border border-line bg-surface p-6 sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </section>
        </div>
        <aside class="lg:col-span-4">
            <div class="border border-line bg-canvas p-6">
                <p class="text-[11px] uppercase tracking-[0.14em] text-ink-muted">{{ __('Locale') }}</p>
                <p class="mt-2 text-sm text-ink-muted">{{ __('Language follows your saved preference and the switcher in the header.') }}</p>
                <div class="mt-4">@include('partials.locale-switcher')</div>
            </div>
        </aside>
    </div>
</x-app-layout>
