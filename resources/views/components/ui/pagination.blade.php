@props(['paginator'])

@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $pages = [1];
    for ($page = max(2, $current - 2); $page <= min($last - 1, $current + 2); $page++) {
        $pages[] = $page;
    }
    if ($last > 1) {
        $pages[] = $last;
    }
    $pages = array_values(array_unique($pages));
@endphp

@if ($paginator->hasPages())
    <nav {{ $attributes->class('flex flex-col items-center justify-between gap-4 border-t border-line px-4 py-4 sm:flex-row') }} aria-label="{{ __('Pagination') }}">
        <p class="text-caption text-ink-muted">
            {{ __('Showing :first–:last of :total', [
                'first' => $paginator->firstItem(),
                'last' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ]) }}
        </p>
        <div class="flex flex-wrap items-center justify-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="inline-flex h-10 min-w-10 cursor-not-allowed items-center justify-center border border-line px-2 text-ink-muted opacity-60" aria-disabled="true">
                    <span class="rtl:rotate-180" aria-hidden="true">‹</span>
                    <span class="sr-only">{{ __('Previous') }}</span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex h-10 min-w-10 items-center justify-center border border-line px-2 text-ink transition hover:border-ink-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                    <span class="rtl:rotate-180" aria-hidden="true">‹</span>
                    <span class="sr-only">{{ __('Previous') }}</span>
                </a>
            @endif

            @foreach ($pages as $page)
                @if (! $loop->first && $page > $pages[$loop->index - 1] + 1)
                    <span class="inline-flex h-10 min-w-8 items-center justify-center text-ink-muted" aria-hidden="true">…</span>
                @endif
                @if ($page === $current)
                    <span class="inline-flex h-10 min-w-10 items-center justify-center bg-brand px-2 font-medium text-ink-inverse" aria-current="page">
                        <span class="sr-only">{{ __('Page') }}</span> {{ $page }}
                    </span>
                @else
                    <a href="{{ $paginator->url($page) }}" class="inline-flex h-10 min-w-10 items-center justify-center border border-line px-2 text-ink transition hover:border-ink-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand" aria-label="{{ __('Page :page', ['page' => $page]) }}">
                        {{ $page }}
                    </a>
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex h-10 min-w-10 items-center justify-center border border-line px-2 text-ink transition hover:border-ink-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                    <span class="rtl:rotate-180" aria-hidden="true">›</span>
                    <span class="sr-only">{{ __('Next') }}</span>
                </a>
            @else
                <span class="inline-flex h-10 min-w-10 cursor-not-allowed items-center justify-center border border-line px-2 text-ink-muted opacity-60" aria-disabled="true">
                    <span class="rtl:rotate-180" aria-hidden="true">›</span>
                    <span class="sr-only">{{ __('Next') }}</span>
                </span>
            @endif
        </div>
    </nav>
@endif
