@props(['lines' => 3])

<div {{ $attributes->class('space-y-3') }} aria-hidden="true">
    @foreach (range(1, (int) $lines) as $line)
        <div @class(['ds-skeleton h-3', 'w-2/3' => $line === (int) $lines, 'w-full' => $line !== (int) $lines])></div>
    @endforeach
</div>
