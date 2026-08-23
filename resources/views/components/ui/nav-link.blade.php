@props(['href', 'active' => false, 'tone' => 'light'])

<a
    href="{{ $href }}"
    {{ $attributes->class([
        'flex items-center gap-2.5 px-3 py-2 text-sm transition',
        'bg-canvas font-medium text-ink' => $active && $tone === 'light',
        'text-ink-muted hover:bg-canvas hover:text-ink' => ! $active && $tone === 'light',
        'bg-white/10 font-medium text-ink-inverse' => $active && $tone === 'dark',
        'text-ink-inverse/65 hover:bg-white/5 hover:text-ink-inverse' => ! $active && $tone === 'dark',
    ]) }}
>
    {{ $slot }}
</a>
