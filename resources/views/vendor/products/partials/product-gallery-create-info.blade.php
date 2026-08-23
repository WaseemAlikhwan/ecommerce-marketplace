<x-ui.card class="mb-6">
    <div class="flex items-start gap-4">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-sm bg-canvas text-brand" aria-hidden="true">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none"><path d="M4 16l4.5-4.5a2 2 0 0 1 2.8 0L16 16m-2-2 1.5-1.5a2 2 0 0 1 2.8 0L20 14M5 20h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="min-w-0 flex-1">
            <h2 class="text-heading-3">{{ __('Product images') }}</h2>
            <p class="mt-2 text-body text-ink-muted">{{ __('Save the product draft first, then upload images from Edit Product.') }}</p>
            <ul class="mt-3 list-disc space-y-1 ps-5 text-caption text-ink-muted">
                <li>{{ __('Up to 8 JPEG, PNG, or WebP images per product') }}</li>
                <li>{{ __('The first uploaded image becomes primary automatically') }}</li>
                <li>{{ __('Optional Arabic and English alt text with product-name fallback') }}</li>
            </ul>
        </div>
    </div>
</x-ui.card>
