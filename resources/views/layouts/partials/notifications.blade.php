<x-dropdown align="right" width="72">
    <x-slot name="trigger">
        <button type="button" class="relative inline-flex h-10 w-10 items-center justify-center rounded-md border border-line bg-elevated text-ink hover:bg-canvas" aria-label="{{ __('Notifications') }}">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M6 9a6 6 0 1112 0c0 4 1.5 5.5 2 6H4c.5-.5 2-2 2-6z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                <path d="M10 18a2 2 0 004 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
            @if ($unreadNotificationCount > 0)
                <span class="absolute end-1 top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-brand px-1 text-[10px] font-semibold text-ink-inverse">
                    {{ $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount }}
                </span>
            @endif
        </button>
    </x-slot>
    <x-slot name="content">
        <div class="border-b border-line px-4 py-3">
            <div class="flex items-center justify-between gap-3">
                <p class="text-label text-ink">{{ __('Notifications') }}</p>
                @if ($unreadNotificationCount > 0)
                    <form method="POST" action="{{ route('account.notifications.read-all') }}">
                        @csrf
                        <button type="submit" class="text-caption text-brand hover:underline">{{ __('Mark all as read') }}</button>
                    </form>
                @endif
            </div>
        </div>
        @if ($notificationRows === [])
            <div class="px-4 py-3">
                <p class="text-caption text-ink-muted">{{ __('No notifications yet.') }}</p>
            </div>
        @else
            <ul class="max-h-80 divide-y divide-line overflow-y-auto" role="list">
                @foreach ($notificationRows as $notification)
                    <li @class(['px-4 py-3', 'bg-canvas/60' => ! $notification['is_read']])>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                @if ($notification['url'] !== null)
                                    <a href="{{ $notification['url'] }}" class="block text-sm text-ink hover:underline">{{ $notification['message'] }}</a>
                                @else
                                    <p class="text-sm text-ink">{{ $notification['message'] }}</p>
                                @endif
                                <p class="mt-1 text-caption text-ink-muted">{{ $notification['created_at_label'] }}</p>
                            </div>
                            @if (! $notification['is_read'])
                                <form method="POST" action="{{ route('account.notifications.read', $notification['id']) }}">
                                    @csrf
                                    <button type="submit" class="shrink-0 text-caption text-ink-muted hover:text-ink">{{ __('Mark as read') }}</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-slot>
</x-dropdown>
