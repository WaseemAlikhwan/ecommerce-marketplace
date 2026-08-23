@props(['label', 'value', 'hint' => null, 'preview' => true])

<x-ui.card {{ $attributes }}>
    <p class="text-caption text-ink-muted">{{ $label }}</p>
    <p class="mt-2 font-numeric text-heading-2 tabular-nums text-ink">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-caption text-ink-muted">{{ $hint }}</p>
    @endif
    @if ($preview)
        <p class="mt-3 text-[11px] uppercase tracking-wide text-ink-muted/80">{{ __('UI preview') }}</p>
    @endif
</x-ui.card>
