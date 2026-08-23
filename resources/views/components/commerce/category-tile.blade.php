@props(['category', 'featured' => false])

<a
    href="{{ $category['url'] }}"
    {{ $attributes->class([
        'group relative block overflow-hidden bg-ink-deep text-ink-inverse',
        'min-h-[20rem] md:min-h-[32rem]' => $featured,
        'min-h-[13rem] md:min-h-[15.5rem]' => ! $featured,
    ]) }}
>
    <span class="absolute inset-0 bg-[radial-gradient(circle_at_20%_10%,rgba(255,255,255,0.16),transparent_32%),linear-gradient(135deg,rgba(196,132,29,0.34),transparent_55%)] transition duration-700 ease-brand group-hover:scale-[1.03]" aria-hidden="true"></span>
    <span class="absolute -end-6 -top-12 font-display text-[12rem] leading-none text-white/[0.06] transition duration-700 group-hover:text-white/[0.1]" aria-hidden="true">{{ mb_substr($category['name'], 0, 1) }}</span>
    <span class="absolute inset-0 ds-pattern opacity-20" aria-hidden="true"></span>
    <span class="absolute inset-0 bg-gradient-to-t from-ink-deep via-transparent to-transparent"></span>
    <span class="absolute inset-x-5 bottom-5 translate-y-0 transition duration-300 ease-brand group-hover:-translate-y-0.5">
        <span class="block text-[11px] uppercase tracking-[0.16em] text-ink-inverse/65">{{ __('Shop') }}</span>
        <span @class(['mt-1 block font-display', 'text-heading-1' => $featured, 'text-heading-3' => ! $featured])>{{ $category['name'] }}</span>
    </span>
</a>
