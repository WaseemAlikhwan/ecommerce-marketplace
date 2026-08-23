@props(['compact' => false, 'inverse' => false])

<div
    {{ $attributes->class([
        'inline-flex items-center p-0.5',
        'rounded-sm border border-white/15 bg-white/5' => $inverse,
        'rounded-sm border border-line bg-elevated' => ! $inverse,
    ]) }}
    role="group"
    aria-label="{{ __('Language') }}"
>
    @foreach (['ar' => __('Arabic'), 'en' => __('English')] as $code => $label)
        <form method="POST" action="{{ route('locale.update') }}" class="contents">
            @csrf
            <input type="hidden" name="locale" value="{{ $code }}">
            <button
                type="submit"
                aria-pressed="{{ app()->getLocale() === $code ? 'true' : 'false' }}"
                @class([
                    'px-2 py-0.5 text-[11px] transition',
                    'bg-ink-inverse text-ink-deep' => $inverse && app()->getLocale() === $code,
                    'text-ink-inverse/70 hover:text-ink-inverse' => $inverse && app()->getLocale() !== $code,
                    'bg-ink-deep text-ink-inverse' => ! $inverse && app()->getLocale() === $code,
                    'text-ink-muted hover:text-ink' => ! $inverse && app()->getLocale() !== $code,
                ])
            >
                {{ $compact ? ($code === 'ar' ? 'ع' : 'EN') : $label }}
            </button>
        </form>
    @endforeach
</div>
