@php
    $galleryReadOnly = ! ($galleryBootstrap['canEdit'] ?? false);
@endphp

<section
    id="product-gallery"
    class="mt-8 scroll-mt-28 space-y-6"
    aria-labelledby="product-gallery-heading"
    tabindex="-1"
    x-data="vendorProductGallery(@js($galleryBootstrap))"
>
    <x-ui.card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 id="product-gallery-heading" class="text-heading-3">{{ __('Product images') }}</h2>
                <p class="mt-1 text-caption text-ink-muted">{{ __('Upload up to 8 JPEG, PNG, or WebP images. The first image becomes primary automatically.') }}</p>
            </div>
            @unless ($galleryReadOnly)
                <p class="text-caption font-medium text-ink-muted" x-text="slotsLabel" aria-live="polite"></p>
            @endunless
        </div>

        <div
            class="mt-4 rounded-sm border border-dashed border-line bg-canvas/60 p-4"
            data-gallery-status
            x-show="statusMessage"
            x-cloak
            :role="statusTone === 'danger' ? 'alert' : 'status'"
            :aria-live="statusTone === 'danger' ? 'assertive' : 'polite'"
            :class="{
                'border-success/30 bg-success/5 text-success': statusTone === 'success',
                'border-danger/30 bg-danger/5 text-danger': statusTone === 'danger',
                'border-brand/30 bg-brand/5 text-brand': statusTone === 'info',
            }"
        >
            <p class="text-caption" x-text="statusMessage"></p>
        </div>

        @unless ($galleryReadOnly)
            <div class="mt-6 space-y-4">
                <div
                    class="rounded-sm border border-line bg-surface p-5 transition focus-within:border-brand/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                    tabindex="0"
                    role="button"
                    :aria-disabled="isFull || uploading"
                    :aria-label="labels.dropzoneLabel"
                    @keydown.enter.prevent="openFilePicker()"
                    @keydown.space.prevent="openFilePicker()"
                    @dragover="onDragOver($event)"
                    @drop="onDrop($event)"
                    @click="openFilePicker()"
                >
                    <div class="flex flex-col items-center gap-3 text-center sm:flex-row sm:text-start">
                        <div class="flex h-12 w-12 items-center justify-center rounded-sm bg-canvas text-brand" aria-hidden="true">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none"><path d="M12 16V8m0 0-3 3m3-3 3 3M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-label">{{ __('Drag and drop or choose files') }}</p>
                            <p class="mt-1 text-caption text-ink-muted">{{ __('JPEG, PNG, and WebP up to 5 MiB each. One file is sent per request.') }}</p>
                        </div>
                        <x-ui.button type="button" variant="secondary" size="sm" @click.stop="openFilePicker()" x-bind:disabled="isFull || uploading">
                            {{ __('Choose files') }}
                        </x-ui.button>
                    </div>
                    <input
                        x-ref="fileInput"
                        type="file"
                        class="sr-only"
                        accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                        multiple
                        @change="onFileInputChange($event)"
                    >
                </div>

                <template x-if="queue.length">
                    <ul class="space-y-2" aria-label="{{ __('Upload queue') }}">
                        <template x-for="item in queue" :key="item.id">
                            <li class="flex items-center gap-3 rounded-sm border border-line px-3 py-2">
                                <img :src="item.previewUrl" alt="" class="h-12 w-12 rounded-sm object-cover" width="48" height="48">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-caption" dir="ltr" x-text="item.name"></p>
                                    <p
                                        class="text-caption"
                                        :class="item.state === 'failed' ? 'text-danger' : 'text-ink-muted'"
                                        :role="item.state === 'failed' ? 'alert' : 'status'"
                                        x-text="queueStateLabel(item)"
                                    ></p>
                                </div>
                                <button
                                    type="button"
                                    class="ds-link text-caption focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                                    x-show="item.state === 'failed' || item.state === 'completed'"
                                    @click="dismissQueueItem(item.id)"
                                    x-text="labels.dismissQueueItem"
                                ></button>
                            </li>
                        </template>
                    </ul>
                </template>

                <noscript>
                    <form method="POST" action="{{ $galleryBootstrap['routes']['upload'] ?? '' }}" enctype="multipart/form-data" class="rounded-sm border border-line p-4">
                        @csrf
                        <label for="noscript-image" class="text-label">{{ __('Upload one image (no JavaScript)') }}</label>
                        <input id="noscript-image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-caption" required>
                        <x-ui.button type="submit" variant="secondary" size="sm" class="mt-3">{{ __('Upload image') }}</x-ui.button>
                    </form>
                </noscript>
            </div>
        @endunless

        <template x-if="images.length === 0">
            <x-ui.empty-state :title="__('No images yet')" class="mt-6">
                {{ __('Add product photos to build your gallery.') }}
            </x-ui.empty-state>
        </template>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3" x-show="images.length" x-cloak>
            <template x-for="(image, index) in images" :key="image.id">
                <article
                    class="overflow-hidden rounded-sm border border-line bg-surface shadow-sm"
                    @dragover.prevent
                    @drop="onDropOnCard($event, index)"
                >
                    <div class="relative aspect-[4/3] bg-canvas">
                        <img
                            x-show="!image.loadFailed"
                            :src="image.url"
                            :alt="image.fallbackAlt"
                            class="h-full w-full object-cover"
                            :width="image.width"
                            :height="image.height"
                            :loading="image.isPrimary ? 'eager' : 'lazy'"
                            x-on:error="markImageFailed(image)"
                        >
                        <div
                            x-show="image.loadFailed"
                            x-cloak
                            class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-ink-muted"
                            data-broken-image-fallback
                        >
                            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 16l4.5-4.5a2 2 0 0 1 2.8 0L16 16m-2-2 1.5-1.5a2 2 0 0 1 2.8 0L20 14M5 20h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <span class="text-caption" x-text="labels.missingFile"></span>
                        </div>
                        <div class="absolute start-3 top-3 flex flex-wrap items-center gap-2">
                            <span
                                x-show="image.isPrimary"
                                class="inline-flex items-center gap-1 rounded-sm bg-ink-deep/85 px-2 py-1 text-[11px] font-medium text-ink-inverse"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.9 6.9L22 10l-5 4.8L18.4 22 12 18.3 5.6 22 7 14.8 2 10l7.1-1.1L12 2z"/></svg>
                                <span x-text="labels.primaryBadge"></span>
                            </span>
                            @unless ($galleryReadOnly)
                                <button
                                    type="button"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-sm bg-ink-inverse/90 text-ink shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                                    draggable="true"
                                    :aria-label="labels.dragHandle"
                                    @click.stop
                                    @dragstart.stop="onHandleDragStart($event, index)"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 7h2v2H8V7Zm6 0h2v2h-2V7ZM8 11h2v2H8v-2Zm6 0h2v2h-2v-2ZM8 15h2v2H8v-2Zm6 0h2v2h-2v-2Z"/></svg>
                                </button>
                            @endunless
                        </div>
                    </div>

                    <div class="space-y-3 p-4">
                        <div class="flex flex-wrap items-center gap-2 text-caption text-ink-muted" dir="ltr">
                            <span x-text="image.dimensionsLabel"></span>
                            <span aria-hidden="true">·</span>
                            <span x-text="image.sizeLabel"></span>
                            <span aria-hidden="true">·</span>
                            <span x-text="image.mimeType"></span>
                        </div>

                        <p class="text-caption">
                            <span class="font-medium">{{ __('Alt text') }}:</span>
                            <span x-text="altStatusLabel(image)"></span>
                        </p>

                        @unless ($galleryReadOnly)
                            <div class="flex flex-wrap gap-2">
                                <x-ui.button type="button" variant="ghost" size="sm" @click="moveEarlier(index)" x-bind:disabled="index === 0 || busyAction !== null">
                                    {{ __('Move earlier') }}
                                </x-ui.button>
                                <x-ui.button type="button" variant="ghost" size="sm" @click="moveLater(index)" x-bind:disabled="index === images.length - 1 || busyAction !== null">
                                    {{ __('Move later') }}
                                </x-ui.button>
                                <x-ui.button type="button" variant="secondary" size="sm" x-show="!image.isPrimary" @click="setPrimary(image)" x-bind:disabled="busyAction !== null">
                                    {{ __('Set as primary') }}
                                </x-ui.button>
                                <x-ui.button type="button" variant="ghost" size="sm" @click="toggleAltEditor(image.id)">
                                    {{ __('Edit alt text') }}
                                </x-ui.button>
                                <x-ui.button type="button" variant="danger" size="sm" @click="removeImage(image)" x-bind:disabled="busyAction !== null">
                                    {{ __('Remove image') }}
                                </x-ui.button>
                            </div>

                            <div x-show="expandedAltId === image.id" x-cloak class="space-y-3 rounded-sm border border-line bg-canvas p-3">
                                <p class="text-caption text-ink-muted">{{ __('If both alt texts are empty, the localized product name is used.') }}</p>
                                <div>
                                    <label class="text-caption font-medium" :for="'alt-ar-' + image.id">{{ __('Arabic alt text') }}</label>
                                    <input :id="'alt-ar-' + image.id" type="text" maxlength="255" dir="rtl" class="ds-input mt-1" x-model="altDrafts[image.id].ar">
                                </div>
                                <div>
                                    <label class="text-caption font-medium" :for="'alt-en-' + image.id">{{ __('English alt text') }}</label>
                                    <input :id="'alt-en-' + image.id" type="text" maxlength="255" dir="ltr" class="ds-input mt-1" x-model="altDrafts[image.id].en">
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <x-ui.button type="button" variant="secondary" size="sm" @click="saveAlt(image)" x-bind:disabled="isBusy('alt-' + image.id)">
                                        {{ __('Save alt text') }}
                                    </x-ui.button>
                                    <x-ui.button type="button" variant="ghost" size="sm" @click="discardAlt(image)">
                                        {{ __('Discard alt text') }}
                                    </x-ui.button>
                                </div>
                            </div>
                        @else
                            <dl class="grid gap-2 text-caption">
                                <div>
                                    <dt class="font-medium">{{ __('Arabic alt text') }}</dt>
                                    <dd dir="rtl" x-text="image.altAr || '—'"></dd>
                                </div>
                                <div>
                                    <dt class="font-medium">{{ __('English alt text') }}</dt>
                                    <dd dir="ltr" x-text="image.altEn || '—'"></dd>
                                </div>
                            </dl>
                        @endunless
                    </div>
                </article>
            </template>
        </div>

        @unless ($galleryReadOnly)
            <div class="mt-6 flex flex-wrap items-center gap-3" data-gallery-order-dirty x-show="orderDirty" x-cloak>
                <p class="text-caption text-warning">{{ __('Gallery order changed. Save to apply.') }}</p>
                <x-ui.button type="button" variant="primary" size="sm" @click="saveOrder()" x-bind:disabled="busyAction !== null">
                    {{ __('Save image order') }}
                </x-ui.button>
                <x-ui.button type="button" variant="ghost" size="sm" @click="discardOrderChanges()">
                    {{ __('Discard order changes') }}
                </x-ui.button>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-3" x-show="staleOrder" x-cloak>
                <x-ui.button type="button" variant="secondary" size="sm" @click="confirmStaleRefresh()">
                    {{ __('Refresh page to resynchronize') }}
                </x-ui.button>
            </div>
        @endunless
    </x-ui.card>
</section>
