<x-storefront-layout
    :title="__('Checkout')"
    :description="__('Confirm your address and place a cash-on-delivery order.')"
    :canonical="route('checkout.create')"
    robots="noindex,follow"
    :nav-categories="$navCategories"
>
    <div class="ds-container py-8 md:py-14">
        <x-ui.breadcrumb :items="[
            ['label' => __('Shop'), 'href' => route('storefront.search')],
            ['label' => __('Cart'), 'href' => route('cart.show')],
            ['label' => __('Checkout')],
        ]" />

        <div class="mt-8">
            <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-ink-muted">{{ __('Checkout') }}</p>
            <h1 class="mt-2 font-display text-heading-1 tracking-tight">{{ __('Confirm order') }}</h1>
            <p class="mt-2 max-w-2xl text-sm text-ink-muted">{{ __('Choose a Syria shipping address, review shipping and COD dues by currency, then place your order.') }}</p>
        </div>

        @if (session('status'))
            <div class="mt-6">
                <x-ui.alert tone="success">{{ session('status') }}</x-ui.alert>
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-6">
                <x-ui.alert tone="danger">{{ $errors->first() }}</x-ui.alert>
            </div>
        @endif

        <div class="mt-10 grid gap-10 lg:grid-cols-12 lg:gap-14"
              x-data="{
                  mode: '{{ old('address_mode', $review->defaultAddressId ? 'existing' : 'new') }}',
                  governorateId: '{{ old('governorate_id', '') }}',
                  citiesByGovernorate: {{ Js::from(collect($review->governorates)->mapWithKeys(fn ($g) => [(string) $g['id'] => $g['cities']])) }}
              }">
            <form id="checkout-form" method="POST" action="{{ route('checkout.store') }}" class="space-y-8 lg:col-span-7">
                @csrf
                <section class="border border-line bg-surface p-6">
                    <h2 class="font-display text-heading-3">{{ __('Shipping address') }}</h2>
                    <p class="mt-2 text-sm text-ink-muted">{{ __('Syria governorate and city only — no area level.') }}</p>

                    @if ($review->addresses !== [])
                        <div class="mt-6 space-y-3">
                            <label class="flex items-center gap-3 text-sm">
                                <input type="radio" name="address_mode" value="existing" x-model="mode" @checked(old('address_mode', $review->defaultAddressId ? 'existing' : 'new') === 'existing')>
                                <span>{{ __('Use a saved address') }}</span>
                            </label>
                            <div x-show="mode === 'existing'" class="space-y-3 ps-7">
                                @foreach ($review->addresses as $address)
                                    <label class="flex cursor-pointer gap-3 border border-line p-4">
                                        <input
                                            type="radio"
                                            name="address_id"
                                            value="{{ $address['id'] }}"
                                            @checked((int) old('address_id', $review->defaultAddressId) === (int) $address['id'])
                                        >
                                        <span>
                                            <span class="block font-medium">{{ $address['label'] }} · {{ $address['recipient_name'] }}</span>
                                            <span class="mt-1 block text-sm text-ink-muted">{{ $address['summary'] }}</span>
                                            <span class="mt-1 block text-sm text-ink-muted">{{ $address['phone'] }}</span>
                                        </span>
                                    </label>
                                @endforeach
                                <x-input-error :messages="$errors->get('address_id')" />
                            </div>
                        </div>
                    @endif

                    <div class="mt-6 space-y-3">
                        <label class="flex items-center gap-3 text-sm">
                            <input type="radio" name="address_mode" value="new" x-model="mode" @checked(old('address_mode', $review->defaultAddressId ? 'existing' : 'new') === 'new' || $review->addresses === [])>
                            <span>{{ __('Add a new address') }}</span>
                        </label>

                        <div x-show="mode === 'new' || {{ $review->addresses === [] ? 'true' : 'false' }}" class="space-y-4 ps-7">
                            <div>
                                <x-input-label for="label" :value="__('Label')" />
                                <x-text-input id="label" name="label" type="text" class="mt-1 block w-full" :value="old('label')" />
                                <x-input-error :messages="$errors->get('label')" />
                            </div>
                            <div>
                                <x-input-label for="recipient_name" :value="__('Recipient name')" />
                                <x-text-input id="recipient_name" name="recipient_name" type="text" class="mt-1 block w-full" :value="old('recipient_name')" />
                                <x-input-error :messages="$errors->get('recipient_name')" />
                            </div>
                            <div>
                                <x-input-label for="phone" :value="__('Phone')" />
                                <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone')" />
                                <x-input-error :messages="$errors->get('phone')" />
                            </div>
                            <div>
                                <x-input-label for="governorate_id" :value="__('Governorate')" />
                                <select id="governorate_id" name="governorate_id" class="ds-input mt-1 block w-full" x-model="governorateId">
                                    <option value="">{{ __('Select governorate') }}</option>
                                    @foreach ($review->governorates as $governorate)
                                        <option value="{{ $governorate['id'] }}" @selected((string) old('governorate_id') === (string) $governorate['id'])>{{ $governorate['name'] }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('governorate_id')" />
                            </div>
                            <div>
                                <x-input-label for="city_id" :value="__('City')" />
                                <select id="city_id" name="city_id" class="ds-input mt-1 block w-full">
                                    <option value="">{{ __('Select city') }}</option>
                                    <template x-for="city in (citiesByGovernorate[governorateId] || [])" :key="city.id">
                                        <option :value="city.id" x-text="city.name" :selected="String(city.id) === '{{ old('city_id') }}'"></option>
                                    </template>
                                </select>
                                <x-input-error :messages="$errors->get('city_id')" />
                            </div>
                            <div>
                                <x-input-label for="line1" :value="__('Address line 1')" />
                                <x-text-input id="line1" name="line1" type="text" class="mt-1 block w-full" :value="old('line1')" />
                                <x-input-error :messages="$errors->get('line1')" />
                            </div>
                            <div>
                                <x-input-label for="line2" :value="__('Address line 2')" />
                                <x-text-input id="line2" name="line2" type="text" class="mt-1 block w-full" :value="old('line2')" />
                                <x-input-error :messages="$errors->get('line2')" />
                            </div>
                            <div>
                                <x-input-label for="notes" :value="__('Delivery notes')" />
                                <textarea id="notes" name="notes" rows="3" class="ds-input mt-1 block w-full">{{ old('notes') }}</textarea>
                                <x-input-error :messages="$errors->get('notes')" />
                            </div>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="is_default" value="1" @checked(old('is_default'))>
                                <span>{{ __('Save as default address') }}</span>
                            </label>
                        </div>
                    </div>
                </section>

                <section class="border border-line bg-surface p-6">
                    <h2 class="font-display text-heading-3">{{ __('Items') }}</h2>
                    <ul class="mt-6 space-y-4">
                        @foreach ($review->lines as $line)
                            <li class="flex items-start justify-between gap-4 border-b border-line pb-4 last:border-0 last:pb-0">
                                <div>
                                    <p class="font-medium">{{ $line->productName }}</p>
                                    <p class="mt-1 text-sm text-ink-muted">{{ $line->storeName }} · {{ __('Qty') }} {{ $line->effectiveQuantity }}</p>
                                </div>
                                <p class="ds-price shrink-0">{{ $line->lineTotal ? \App\Support\Money::formatFromMinor((int) $line->lineTotal['amount_minor'], (int) $line->lineTotal['exponent']).' '.$line->lineTotal['currency_code'] : '—' }}</p>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </form>

            <aside class="lg:col-span-5">
                <div class="border border-line bg-surface p-6 lg:sticky lg:top-28">
                    <h2 class="font-display text-heading-3">{{ __('Order summary') }}</h2>
                    <p class="mt-2 text-sm text-ink-muted">{{ __('Cash on delivery. Mixed currencies stay separate.') }}</p>

                    <div class="mt-6 border-b border-line pb-4">
                        <p class="text-sm font-medium">{{ __('Coupon') }}</p>
                        @if ($review->appliedCouponCode)
                            <div class="mt-3 flex items-start justify-between gap-3 text-sm">
                                <div>
                                    <p class="font-medium">{{ $review->appliedCouponCode }}</p>
                                    @if ($review->couponDiscount)
                                        <p class="mt-1 text-ink-muted">{{ __('Discount') }}: −{{ $review->couponDiscount['label'] }}</p>
                                    @endif
                                </div>
                                <form method="POST" action="{{ route('checkout.coupon.remove') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-ink-muted underline hover:text-ink">{{ __('Remove coupon') }}</button>
                                </form>
                            </div>
                        @else
                            <form method="POST" action="{{ route('checkout.coupon.apply') }}" class="mt-3 flex gap-2">
                                @csrf
                                <input
                                    type="text"
                                    name="code"
                                    value="{{ old('code') }}"
                                    maxlength="64"
                                    autocomplete="off"
                                    class="ds-input block w-full"
                                    placeholder="{{ __('Enter coupon code') }}"
                                    aria-label="{{ __('Coupon code') }}"
                                >
                                <x-ui.button variant="secondary" type="submit">{{ __('Apply coupon') }}</x-ui.button>
                            </form>
                        @endif
                    </div>

                    <div class="mt-6 space-y-4">
                        @foreach ($review->vendorGroups as $group)
                            <div class="border-b border-line pb-4 last:border-0 last:pb-0">
                                <p class="font-medium">{{ $group['store_name'] }}</p>
                                <dl class="mt-3 space-y-2 text-sm">
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-ink-muted">{{ __('Items') }}</dt>
                                        <dd>{{ $group['items_subtotal']['label'] }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-ink-muted">{{ __('Shipping') }}</dt>
                                        <dd>{{ $group['shipping']['label'] }}</dd>
                                    </div>
                                    @if (! empty($group['discount']))
                                        <div class="flex justify-between gap-3">
                                            <dt class="text-ink-muted">{{ __('Discount') }}</dt>
                                            <dd>−{{ $group['discount']['label'] }}</dd>
                                        </div>
                                    @endif
                                    <div class="flex justify-between gap-3 font-medium">
                                        <dt>{{ __('COD due') }}</dt>
                                        <dd>{{ $group['due']['label'] }}</dd>
                                    </div>
                                </dl>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 border-t border-line pt-4">
                        <p class="text-sm font-medium">{{ __('Total COD dues') }}</p>
                        <ul class="mt-2 space-y-1">
                            @foreach ($review->codDues as $due)
                                <li class="ds-price text-lg">{{ $due['label'] }}</li>
                            @endforeach
                        </ul>
                        <p class="mt-3 text-caption text-ink-muted">{{ __('Payment status after placement: COD pending.') }}</p>
                    </div>

                    <div class="mt-8">
                        <x-ui.button variant="primary" class="w-full" type="submit" form="checkout-form">{{ __('Place order') }}</x-ui.button>
                        <p class="mt-3 text-center text-caption text-ink-muted">
                            <a href="{{ route('cart.show') }}" class="underline">{{ __('Back to cart') }}</a>
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</x-storefront-layout>
