@props(['action', 'catalog', 'filters', 'clearUrl', 'omit' => []])

<div class="contents" x-data="storefrontDialog()">
    <div class="lg:hidden">
        <button
            type="button"
            class="flex w-full items-center justify-between border border-line bg-surface px-4 py-3 text-sm font-medium"
            x-cloak
            x-ref="trigger"
            @click="showDialog()"
            :aria-expanded="open.toString()"
            aria-controls="catalog-filter-dialog"
        >
            <span>{{ __('Filters') }}</span>
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M7 12h10M10 17h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        </button>
        <noscript>
            <details class="border border-line bg-surface">
                <summary class="cursor-pointer px-4 py-3 text-sm font-medium">{{ __('Filters') }}</summary>
                <div class="border-t border-line p-4">
                    @include('storefront.partials.filter-fields', [
                        'action' => $action,
                        'catalog' => $catalog,
                        'filters' => $filters,
                        'clearUrl' => $clearUrl,
                        'omit' => $omit,
                        'idPrefix' => 'nojs-mobile-filter',
                    ])
                </div>
            </details>
        </noscript>

        <template x-teleport="body">
            <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 bg-ink-deep/45" @click="closeDialog()"></div>
        </template>
        <template x-teleport="body">
            <aside
                id="catalog-filter-dialog"
                x-show="open"
                x-cloak
                x-ref="dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="catalog-filter-title"
                tabindex="-1"
                x-transition:enter="transition duration-300 ease-brand"
                x-transition:enter-start="-translate-x-full rtl:translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition duration-200"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="-translate-x-full rtl:translate-x-full"
                class="fixed inset-y-0 start-0 z-50 w-[21rem] max-w-[90vw] overflow-y-auto bg-surface p-5"
                @keydown="handleDialogKeydown($event)"
            >
                <div class="mb-5 flex items-center justify-between border-b border-line pb-4">
                    <h2 id="catalog-filter-title" class="font-display text-heading-3">{{ __('Filters') }}</h2>
                    <button type="button" class="ds-icon-btn" @click="closeDialog()" aria-label="{{ __('Close') }}">×</button>
                </div>
                @include('storefront.partials.filter-fields', [
                    'action' => $action,
                    'catalog' => $catalog,
                    'filters' => $filters,
                    'clearUrl' => $clearUrl,
                    'omit' => $omit,
                    'idPrefix' => 'mobile-filter',
                    'isMobile' => true,
                ])
            </aside>
        </template>
    </div>

    <aside class="hidden lg:col-span-3 lg:block" aria-label="{{ __('Filters') }}">
        <div class="sticky top-28 border border-line bg-surface p-5">
            <h2 class="mb-5 border-b border-line pb-4 font-display text-heading-3">{{ __('Filters') }}</h2>
            @include('storefront.partials.filter-fields', [
                'action' => $action,
                'catalog' => $catalog,
                'filters' => $filters,
                'clearUrl' => $clearUrl,
                'omit' => $omit,
                'idPrefix' => 'desktop-filter',
            ])
        </div>
    </aside>
</div>
