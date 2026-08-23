@props(['compact' => false, 'inverse' => false])

<a href="{{ route('home') }}" {{ $attributes->class(['group inline-flex items-center gap-2.5 no-underline', 'text-ink-inverse' => $inverse, 'text-ink' => ! $inverse]) }}>
    <span @class([
        'inline-flex h-9 w-9 items-center justify-center',
        'bg-ink-inverse/10 text-ink-inverse' => $inverse,
        'bg-ink-deep text-ink-inverse' => ! $inverse,
    ])>
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 2.6 14.2 8l5.8.4-4.4 3.6 1.5 5.6L12 14.8 6.9 17.6 8.4 12 4 8.4 9.8 8 12 2.6Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
        </svg>
    </span>
    @unless ($compact)
        <span class="leading-none">
            <span class="block font-display text-[1.05rem] font-semibold tracking-tight">{{ __('Sham Market') }}</span>
            <span @class(['mt-1 block text-[10px] uppercase tracking-[0.18em]', 'text-ink-inverse/60' => $inverse, 'text-ink-muted' => ! $inverse])>{{ __('Syria') }}</span>
        </span>
    @endunless
</a>
