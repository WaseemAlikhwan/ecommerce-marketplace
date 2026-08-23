<x-dropdown align="right" width="48">
    <x-slot name="trigger">
        <button type="button" class="inline-flex items-center gap-2 rounded-md border border-line bg-elevated px-2.5 py-1.5 text-sm text-ink hover:bg-canvas">
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-brand-soft text-caption font-semibold text-brand">
                {{ mb_substr(auth()->user()->name, 0, 1) }}
            </span>
            <span class="hidden max-w-[8rem] truncate sm:inline">{{ auth()->user()->name }}</span>
            <svg class="h-4 w-4 text-ink-muted" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
        </button>
    </x-slot>
    <x-slot name="content">
        <x-dropdown-link :href="route('dashboard')">{{ __('Account') }}</x-dropdown-link>
        <x-dropdown-link :href="route('profile.edit')">{{ __('Profile') }}</x-dropdown-link>
        @if (auth()->user()->isStaff())
            <x-dropdown-link :href="route('admin.dashboard')">{{ __('Admin console') }}</x-dropdown-link>
        @endif
        @if (auth()->user()->canAccessVendorPanel())
            <x-dropdown-link :href="route('vendor.dashboard')">{{ __('Seller workspace') }}</x-dropdown-link>
        @else
            <x-dropdown-link :href="route('account.vendor-application')">{{ __('Become a vendor') }}</x-dropdown-link>
        @endif
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="block w-full px-4 py-2 text-start text-sm text-ink hover:bg-canvas">{{ __('Log Out') }}</button>
        </form>
    </x-slot>
</x-dropdown>
