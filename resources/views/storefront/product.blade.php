@php
    $breadcrumbs = [['label' => __('Shop'), 'href' => route('storefront.search')]];
    foreach ($product['breadcrumbs'] as $crumb) {
        $breadcrumbs[] = ['label' => $crumb['name'], 'href' => $crumb['url']];
    }
    $breadcrumbs[] = ['label' => $product['name']];
    $variantPayload = [
        'attributes' => $product['attributes'],
        'variants' => $product['variants'],
        'defaultVariant' => $product['default_variant'],
        'priceRangeLabel' => $product['price_range_label'],
        'messages' => [
            'incomplete' => __('Choose every option to see availability.'),
            'unavailable' => __('This combination is unavailable. Choose another option.'),
            'inStock' => __('In stock'),
            'outOfStock' => __('Out of stock'),
        ],
    ];
@endphp

@php
    $ogImage = null;
    foreach ($product['gallery'] as $image) {
        if ($image['is_primary']) {
            $ogImage = $image['url'];
            break;
        }
    }
    $ogImage ??= $product['gallery'][0]['url'] ?? null;
@endphp

<x-storefront-layout
    :title="$product['name']"
    :description="$product['short_description'] ?? $product['description']"
    :canonical="$product['url']"
    robots="index,follow"
    :og-title="$product['name']"
    :og-description="$product['short_description'] ?? $product['description']"
    og-type="product"
    :og-url="$product['url']"
    :og-image="$ogImage"
    :nav-categories="$navCategories"
>
    <div class="ds-container py-6 md:py-12">
        <x-ui.breadcrumb :items="$breadcrumbs" />

        <div class="mt-6 grid items-start gap-10 lg:grid-cols-12 lg:gap-14">
            <div class="lg:col-span-7">
                <x-commerce.gallery :images="$product['gallery']" />
            </div>

            <div class="lg:col-span-5 lg:pt-1">
                <a href="{{ $product['store']['url'] }}" class="text-[11px] uppercase tracking-[0.16em] text-ink-muted transition hover:text-ink">{{ $product['store']['name'] }}</a>
                <h1 class="mt-2 font-display text-heading-1 tracking-tight">{{ $product['name'] }}</h1>
                @if ($product['brand'])
                    <p class="mt-2 text-caption text-ink-muted">{{ __('Brand') }}: {{ $product['brand']['name'] }}</p>
                @endif

                @if ($product['type'] === 'variable')
                    <section
                        class="mt-7 border-y border-line py-5"
                        x-data="storefrontVariantSelector(@js($variantPayload))"
                        aria-labelledby="product-options"
                    >
                        <h2 id="product-options" class="font-display text-heading-3">{{ __('Choose options') }}</h2>

                        <div class="mt-5 space-y-5">
                            @foreach ($product['attributes'] as $attribute)
                                <fieldset class="rounded-sm border border-line/80 p-3">
                                    <legend class="px-1 text-caption font-medium text-ink">
                                        {{ $attribute['name'] }}
                                        <span class="ms-1 font-normal text-ink-muted" aria-live="polite" x-text="selectedValueLabel(@js($attribute['code']))"></span>
                                    </legend>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($attribute['values'] as $value)
                                            <button
                                                type="button"
                                                class="border px-3 py-2 text-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-not-allowed disabled:border-line disabled:bg-canvas disabled:text-ink-muted disabled:opacity-60"
                                                :class="isSelected(@js($attribute['code']), @js($value['code'])) ? 'border-ink-deep bg-ink-deep text-ink-inverse' : 'border-line bg-surface text-ink hover:border-ink/40'"
                                                :disabled="!isValueAvailable(@js($attribute['code']), @js($value['code']))"
                                                :aria-disabled="(!isValueAvailable(@js($attribute['code']), @js($value['code']))).toString()"
                                                :aria-pressed="isSelected(@js($attribute['code']), @js($value['code'])).toString()"
                                                @click="select(@js($attribute['code']), @js($value['code']))"
                                            >{{ $value['name'] }}</button>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endforeach
                        </div>

                        <div class="mt-6 border-t border-line pt-5">
                            <div class="flex flex-wrap items-baseline gap-2">
                                <span class="ds-price text-[1.65rem] leading-none" x-text="selectedPriceLabel">{{ $product['selected_price_label'] }}</span>
                                <span class="text-caption text-ink-muted line-through" x-show="selectedCompareAtLabel" x-text="selectedCompareAtLabel">{{ $product['selected_compare_at_label'] }}</span>
                            </div>
                            <p
                                class="mt-3 border-s-2 ps-3 text-sm font-medium"
                                :class="state === 'in_stock' ? 'border-success text-success' : (state === 'out_of_stock' || state === 'unavailable' ? 'border-danger text-danger' : 'border-line text-ink-muted')"
                                role="status"
                                aria-live="polite"
                                aria-atomic="true"
                                x-text="statusLabel"
                            >{{ $product['in_stock'] ? __('In stock') : __('Out of stock') }}</p>
                        </div>

                        <form
                            method="post"
                            action="{{ route('cart.items.store') }}"
                            class="mt-6 flex flex-wrap items-center gap-3"
                            x-show="state === 'in_stock'"
                            x-cloak
                        >
                            @csrf
                            <input type="hidden" name="variant_id" :value="selectedVariant?.id || ''">
                            <x-commerce.qty name="quantity" :value="1" :min="1" />
                            <x-ui.button variant="primary" class="min-w-[10rem]" x-bind:disabled="!selectedVariant">{{ __('Add to cart') }}</x-ui.button>
                        </form>
                        <p class="mt-4 text-sm text-ink-muted" x-show="state !== 'in_stock'" x-cloak x-text="statusLabel"></p>

                        <noscript>
                            <div class="mt-6 border-t border-line pt-5">
                                <h3 class="text-sm font-medium text-ink">{{ __('Available combinations') }}</h3>
                                <ul class="mt-3 max-h-80 space-y-2 overflow-y-auto text-sm">
                                    @foreach (array_slice($product['variants'], 0, 48) as $variant)
                                        <li class="flex flex-wrap items-center justify-between gap-3 border border-line px-3 py-2">
                                            <span>{{ implode(' · ', array_column($variant['selection'], 'value_name')) }}</span>
                                            <span>
                                                {{ $variant['price_label'] }}
                                                · {{ $variant['in_stock'] ? __('In stock') : __('Out of stock') }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </noscript>
                    </section>
                @else
                    <div class="mt-7 border-y border-line py-5">
                        <x-commerce.price :label="$product['selected_price_label']" :compare-label="$product['selected_compare_at_label']" size="lg" />
                        <p class="mt-2 text-sm {{ $product['in_stock'] ? 'text-success' : 'text-ink-muted' }}">
                            {{ $product['in_stock'] ? __('In stock') : __('Out of stock') }}
                        </p>
                        @if ($product['in_stock'] && ($product['default_variant']['id'] ?? null))
                            <form method="post" action="{{ route('cart.items.store') }}" class="mt-6 flex flex-wrap items-center gap-3">
                                @csrf
                                <input type="hidden" name="variant_id" value="{{ $product['default_variant']['id'] }}">
                                <x-commerce.qty name="quantity" :value="1" :min="1" />
                                <x-ui.button variant="primary" class="min-w-[10rem]">{{ __('Add to cart') }}</x-ui.button>
                            </form>
                        @endif
                    </div>
                @endif

                <div class="mt-6">
                    @if ($wishlistItemId)
                        <form method="post" action="{{ route('account.wishlist.destroy', $wishlistItemId) }}">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" variant="secondary">{{ __('Remove from wishlist') }}</x-ui.button>
                        </form>
                    @else
                        <form method="post" action="{{ route('account.wishlist.store', $product['id']) }}">
                            @csrf
                            <x-ui.button type="submit" variant="secondary">{{ __('Add to wishlist') }}</x-ui.button>
                        </form>
                    @endif
                </div>

                @if ($product['short_description'])
                    <p class="mt-6 max-w-md text-sm leading-relaxed text-ink-muted" dir="auto">{{ $product['short_description'] }}</p>
                @endif

                @if ($product['description'])
                    <div class="mt-6 border-t border-line pt-6">
                        <h2 class="font-display text-heading-3">{{ __('Description') }}</h2>
                        <div class="mt-3 whitespace-pre-line text-sm leading-relaxed text-ink-muted" dir="auto">{{ $product['description'] }}</div>
                    </div>
                @endif

                <div class="mt-8">
                    <x-commerce.seller-block :store="$product['store']" />
                </div>
            </div>
        </div>

        <section class="mt-16 border-t border-line pt-10 md:mt-20" aria-labelledby="product-reviews-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="product-reviews-heading" class="font-display text-heading-2">{{ __('Reviews') }}</h2>
                    @if (($reviewAggregate['count'] ?? 0) > 0)
                        <p class="mt-2 text-sm text-ink-muted">
                            {{ __('Average rating :rating from :count reviews', [
                                'rating' => $reviewAggregate['average'],
                                'count' => $reviewAggregate['count'],
                            ]) }}
                        </p>
                    @else
                        <p class="mt-2 text-sm text-ink-muted">{{ __('No reviews yet.') }}</p>
                    @endif
                </div>
            </div>

            @if (session('status'))
                <x-ui.alert tone="success" class="mt-6">{{ session('status') }}</x-ui.alert>
            @endif

            @if ($errors->has('review'))
                <x-ui.alert tone="danger" class="mt-6">{{ $errors->first('review') }}</x-ui.alert>
            @endif

            @if (($approvedReviews ?? []) !== [])
                <ul class="mt-8 space-y-5">
                    @foreach ($approvedReviews as $review)
                        <li class="border border-line bg-surface p-4">
                            <p class="text-sm font-medium text-ink">{{ __('Rating') }}: {{ $review['rating'] }}/5</p>
                            @if (! empty($review['body']))
                                <p class="mt-2 text-sm leading-relaxed text-ink-muted" dir="auto">{{ $review['body'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-10 max-w-xl border border-line bg-surface p-5">
                @auth
                    @if ($ownReview)
                        <h3 class="font-display text-heading-3">{{ __('Update your review') }}</h3>
                        @if ($ownReview['is_pending'])
                            <p class="mt-2 text-sm text-ink-muted">{{ __('Your review is pending moderation.') }}</p>
                        @elseif ($ownReview['is_rejected'])
                            <p class="mt-2 text-sm text-ink-muted">{{ __('Your review was rejected. You may edit and resubmit.') }}</p>
                        @elseif ($ownReview['is_approved'])
                            <p class="mt-2 text-sm text-ink-muted">{{ __('Editing will send your review back for moderation.') }}</p>
                        @endif
                        <form method="post" action="{{ route('account.reviews.update', $ownReview['id']) }}" class="mt-4 space-y-4">
                            @csrf
                            @method('PUT')
                            <div>
                                <label for="review-rating" class="text-sm font-medium text-ink">{{ __('Rating') }}</label>
                                <select id="review-rating" name="rating" required class="mt-1 block w-full border border-line bg-canvas px-3 py-2 text-sm">
                                    @for ($n = 5; $n >= 1; $n--)
                                        <option value="{{ $n }}" @selected((int) old('rating', $ownReview['rating']) === $n)>{{ $n }}</option>
                                    @endfor
                                </select>
                                @error('rating')
                                    <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="review-body" class="text-sm font-medium text-ink">{{ __('Your review') }}</label>
                                <textarea id="review-body" name="body" rows="4" maxlength="2000" class="mt-1 block w-full border border-line bg-canvas px-3 py-2 text-sm" dir="auto">{{ old('body', $ownReview['body']) }}</textarea>
                                @error('body')
                                    <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                                @enderror
                            </div>
                            <x-ui.button type="submit" variant="primary">{{ __('Save review') }}</x-ui.button>
                        </form>
                    @elseif ($canReview ?? false)
                        <h3 class="font-display text-heading-3">{{ __('Write a review') }}</h3>
                        <form method="post" action="{{ route('account.reviews.store', $product['id']) }}" class="mt-4 space-y-4">
                            @csrf
                            <div>
                                <label for="review-rating" class="text-sm font-medium text-ink">{{ __('Rating') }}</label>
                                <select id="review-rating" name="rating" required class="mt-1 block w-full border border-line bg-canvas px-3 py-2 text-sm">
                                    <option value="" disabled @selected(old('rating') === null)>{{ __('Choose a rating') }}</option>
                                    @for ($n = 5; $n >= 1; $n--)
                                        <option value="{{ $n }}" @selected((string) old('rating') === (string) $n)>{{ $n }}</option>
                                    @endfor
                                </select>
                                @error('rating')
                                    <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="review-body" class="text-sm font-medium text-ink">{{ __('Your review') }}</label>
                                <textarea id="review-body" name="body" rows="4" maxlength="2000" class="mt-1 block w-full border border-line bg-canvas px-3 py-2 text-sm" dir="auto">{{ old('body') }}</textarea>
                                @error('body')
                                    <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                                @enderror
                            </div>
                            <x-ui.button type="submit" variant="primary">{{ __('Submit review') }}</x-ui.button>
                        </form>
                    @else
                        <p class="text-sm text-ink-muted">{{ __('You can review this product after a delivered purchase.') }}</p>
                    @endif
                @else
                    <h3 class="font-display text-heading-3">{{ __('Write a review') }}</h3>
                    <p class="mt-2 text-sm text-ink-muted">{{ __('Sign in to write a review.') }}</p>
                    <div class="mt-4">
                        <x-ui.button :href="route('login')" variant="secondary">{{ __('Log in') }}</x-ui.button>
                    </div>
                @endauth
            </div>
        </section>

        @if ($product['related'] !== [])
            <section class="mt-20 md:mt-28">
                <x-commerce.section-heading :kicker="__('Continue')" :title="__('You may also like')" />
                <div class="grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($product['related'] as $item)
                        <x-commerce.product-card :product="$item" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-storefront-layout>
