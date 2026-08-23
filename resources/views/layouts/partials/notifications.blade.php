<x-dropdown align="right" width="72">
    <x-slot name="trigger">
        <button type="button" class="relative inline-flex h-10 w-10 items-center justify-center rounded-md border border-line bg-elevated text-ink hover:bg-canvas" aria-label="{{ __('Notifications') }}">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 9a6 6 0 1112 0c0 4 1.5 5.5 2 6H4c.5-.5 2-2 2-6z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                <path d="M10 18a2 2 0 004 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
        </button>
    </x-slot>
    <x-slot name="content">
        <div class="px-4 py-3">
            <p class="text-label text-ink">{{ __('Notifications') }}</p>
            <p class="mt-1 text-caption text-ink-muted">{{ __('No notifications yet. This tray is a visual placeholder.') }}</p>
        </div>
    </x-slot>
</x-dropdown>
