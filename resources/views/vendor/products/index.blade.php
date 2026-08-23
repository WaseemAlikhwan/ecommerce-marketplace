<x-vendor-layout :title="__('Products')">
    <x-slot name="header">{{ __('Products') }}</x-slot>

    <x-ui.page-header :title="__('Products')" :description="__('Create and manage simple and variable products for your store. Open a product to review readiness and publish.')">
        <x-slot:breadcrumb>
            <x-ui.breadcrumb :items="[
                ['label' => __('Vendor'), 'href' => route('vendor.dashboard')],
                ['label' => __('Products')],
            ]" />
        </x-slot:breadcrumb>
        <x-slot:actions>
            <x-ui.button :href="route('vendor.products.create')" variant="primary">{{ __('Add product') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))
        <x-ui.alert tone="success" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    <div class="ds-table-wrap">
        <table class="ds-table">
            <thead>
                <tr>
                    <th scope="col">{{ __('Name') }}</th>
                    <th scope="col">{{ __('Type') }}</th>
                    <th scope="col">{{ __('SKU') }}</th>
                    <th scope="col">{{ __('Variants') }}</th>
                    <th scope="col">{{ __('Price') }}</th>
                    <th scope="col">{{ __('Status') }}</th>
                    <th scope="col">{{ __('Currency') }}</th>
                    <th scope="col">{{ __('Updated') }}</th>
                    <th scope="col">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td>
                            <div class="flex items-start gap-3">
                                <div class="h-14 w-14 shrink-0 overflow-hidden rounded-sm border border-line bg-canvas" x-data="{ failed: false }">
                                    @if ($product->primaryImage)
                                        <img
                                            src="{{ $product->primaryImage->url() }}"
                                            alt="{{ \App\Support\VendorProductGalleryState::thumbnailAlt($product) }}"
                                            class="h-full w-full object-cover"
                                            width="56"
                                            height="56"
                                            loading="lazy"
                                            x-show="!failed"
                                            x-on:error="failed = true"
                                        >
                                        <div x-show="failed" x-cloak class="flex h-full w-full flex-col items-center justify-center text-ink-muted" data-broken-image-fallback>
                                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 16l4.5-4.5a2 2 0 0 1 2.8 0L16 16m-2-2 1.5-1.5a2 2 0 0 1 2.8 0L20 14M5 20h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            <span class="sr-only">{{ __('Image unavailable') }}</span>
                                        </div>
                                    @else
                                        <div class="flex h-full w-full items-center justify-center text-ink-muted" aria-hidden="true">
                                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none"><path d="M4 16l4.5-4.5a2 2 0 0 1 2.8 0L16 16m-2-2 1.5-1.5a2 2 0 0 1 2.8 0L20 14M5 20h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </div>
                                        <span class="sr-only">{{ __('No product image') }}</span>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('vendor.products.edit', $product) }}" class="ds-link">{{ $product->name() }}</a>
                                        @if ($product->hasMissingTranslation())
                                            <x-ui.badge tone="warning">{{ __('Missing translation') }}</x-ui.badge>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-caption text-ink-muted" dir="ltr">{{ $product->slug }}</p>
                                </div>
                            </div>
                        </td>
                        <td>
                            <x-ui.badge :tone="$product->type === \App\Enums\ProductType::Variable ? 'brand' : 'neutral'">
                                {{ $product->type->label() }}
                            </x-ui.badge>
                        </td>
                        <td dir="ltr" class="text-caption">{{ $product->defaultVariant?->sku ?? '—' }}</td>
                        <td>{{ $product->variants->count() }}</td>
                        <td dir="ltr" class="font-numeric">{{ $product->formattedLivePriceRange() }}</td>
                        <td>
                            <x-ui.badge :tone="$product->status->badgeTone()">{{ $product->status->label() }}</x-ui.badge>
                        </td>
                        <td dir="ltr">{{ $product->currency_code }}</td>
                        <td>{{ $product->updated_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                        <td>
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('vendor.products.edit', $product) }}" class="ds-link text-caption">{{ __('Edit') }}</a>
                                @can('archive', $product)
                                    <form method="POST" action="{{ route('vendor.products.archive', $product) }}" onsubmit="return confirm(@js(__('Archive this product?')))">
                                        @csrf
                                        <button type="submit" class="ds-link text-caption">{{ __('Archive') }}</button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-10">
                            <x-ui.empty-state
                                :title="__('No products yet')"
                                :action="__('Add product')"
                                :href="route('vendor.products.create')"
                            >
                                {{ __('Start with a simple or variable draft. Variants keep SKU, price, and quantity for every sellable unit.') }}
                            </x-ui.empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($products->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $products->links() }}</div>
        @endif
    </div>
</x-vendor-layout>
