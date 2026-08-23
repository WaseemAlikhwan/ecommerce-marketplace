@props(['tabs' => [], 'active' => null])

<div {{ $attributes }}>
    <div class="flex gap-1 overflow-x-auto border-b border-line" role="tablist">
        @foreach ($tabs as $tab)
            <a
                href="{{ $tab['href'] }}"
                @class([
                    'whitespace-nowrap px-4 py-2.5 text-label transition',
                    'border-b-2 border-brand text-ink' => ($active ?? null) === ($tab['key'] ?? null),
                    'border-b-2 border-transparent text-ink-muted hover:text-ink' => ($active ?? null) !== ($tab['key'] ?? null),
                ])
            >
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>
    <div class="pt-5">
        {{ $slot }}
    </div>
</div>
