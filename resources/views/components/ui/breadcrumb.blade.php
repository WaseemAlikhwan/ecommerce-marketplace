@props(['items' => []])

<nav {{ $attributes->class('mb-2') }} aria-label="{{ __('Breadcrumb') }}">
    <ol class="flex flex-wrap items-center gap-1.5 text-caption text-ink-muted">
        @foreach ($items as $index => $item)
            <li class="inline-flex items-center gap-1.5">
                @if (! empty($item['href']) && ! $loop->last)
                    <a href="{{ $item['href'] }}" class="hover:text-ink">{{ $item['label'] }}</a>
                @else
                    <span @class(['text-ink' => $loop->last])>{{ $item['label'] }}</span>
                @endif
                @unless ($loop->last)
                    <svg class="h-3 w-3 text-line rtl:rotate-180" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                        <path d="M4.5 2.5L8 6 4.5 9.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                @endunless
            </li>
        @endforeach
    </ol>
</nav>
