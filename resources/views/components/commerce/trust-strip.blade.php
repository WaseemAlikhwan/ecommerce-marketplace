@props(['items' => null])

@php
    $items = $items ?? [
        ['title' => __('Secure checkout'), 'text' => __('Pay with cash on delivery (COD).'), 'icon' => 'lock'],
        ['title' => __('Trusted sellers'), 'text' => __('Independent Syrian stores'), 'icon' => 'store'],
        ['title' => __('Reliable delivery'), 'text' => __('Delivery across Syrian cities.'), 'icon' => 'truck'],
        ['title' => __('Customer support'), 'text' => __('A person, not a ticket maze'), 'icon' => 'chat'],
    ];
@endphp

<ul {{ $attributes->class('grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-4') }} role="list">
    @foreach ($items as $item)
        <li class="flex items-start gap-3">
            <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center text-brand" aria-hidden="true">
                @switch($item['icon'] ?? 'lock')
                    @case('store')
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="M4 10V20h16V10M3 7h18l-1.5 3H4.5L3 7Z" stroke="currentColor" stroke-width="1.5"/><path d="M9 20v-6h6v6" stroke="currentColor" stroke-width="1.5"/></svg>
                        @break
                    @case('truck')
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="M3 7h11v10H3V7Zm11 3h4l3 3v4h-7V10Z" stroke="currentColor" stroke-width="1.5"/><circle cx="7" cy="18" r="1.4" fill="currentColor"/><circle cx="17" cy="18" r="1.4" fill="currentColor"/></svg>
                        @break
                    @case('chat')
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="M5 6h14v10H8l-3 3V6Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                        @break
                    @default
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><rect x="5" y="10" width="14" height="10" stroke="currentColor" stroke-width="1.5"/><path d="M8 10V8a4 4 0 0 1 8 0v2" stroke="currentColor" stroke-width="1.5"/></svg>
                @endswitch
            </span>
            <span>
                <span class="block text-sm font-medium text-ink">{{ $item['title'] }}</span>
                @if (! empty($item['text']))
                    <span class="mt-0.5 block text-caption text-ink-muted">{{ $item['text'] }}</span>
                @endif
            </span>
        </li>
    @endforeach
</ul>
