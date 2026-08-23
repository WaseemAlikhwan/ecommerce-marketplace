@php
    $criteria = $catalog['criteria'];
    $omit = $omit ?? [];
    $idPrefix = $idPrefix ?? 'catalog';
    $isMobile = $isMobile ?? false;
    $hasOption = static function (array $options, string $key, ?string $value): bool {
        if ($value === null) {
            return false;
        }

        foreach ($options as $option) {
            if (($option[$key] ?? null) === $value) {
                return true;
            }
        }

        return false;
    };
@endphp

<form
    action="{{ $action }}"
    method="get"
    class="space-y-5"
    x-data="{ currency: @js($criteria['currency'] ?? ''), sort: @js($criteria['sort'] ?? 'newest') }"
    @if ($isMobile) x-on:submit="closeDialog(false)" @endif
>
    <div>
        <label for="{{ $idPrefix }}-q" class="mb-1.5 block text-caption font-medium text-ink">{{ __('Search') }}</label>
        <input id="{{ $idPrefix }}-q" type="search" name="q" value="{{ $criteria['q'] }}" class="w-full border-line bg-surface text-sm focus:border-brand focus:ring-brand" placeholder="{{ __('Search products') }}">
    </div>

    @unless (in_array('category', $omit, true))
        <div>
            <label for="{{ $idPrefix }}-category" class="mb-1.5 block text-caption font-medium text-ink">{{ __('Category') }}</label>
            <select id="{{ $idPrefix }}-category" name="category" class="w-full border-line bg-surface text-sm focus:border-brand focus:ring-brand">
                <option value="">{{ __('All categories') }}</option>
                @if ($criteria['category'] !== null && ! $hasOption($filters['categories'], 'slug', $criteria['category']))
                    <option value="{{ $criteria['category'] }}" selected>{{ $criteria['category'] }}</option>
                @endif
                @foreach ($filters['categories'] as $option)
                    <option value="{{ $option['slug'] }}" @selected($criteria['category'] === $option['slug'])>{{ $option['label'] ?? $option['name'] }}</option>
                @endforeach
            </select>
        </div>
    @endunless

    <div>
        <label for="{{ $idPrefix }}-brand" class="mb-1.5 block text-caption font-medium text-ink">{{ __('Brand') }}</label>
        <select id="{{ $idPrefix }}-brand" name="brand" class="w-full border-line bg-surface text-sm focus:border-brand focus:ring-brand">
            <option value="">{{ __('All brands') }}</option>
            @if ($criteria['brand'] !== null && ! $hasOption($filters['brands'], 'slug', $criteria['brand']))
                <option value="{{ $criteria['brand'] }}" selected>{{ $criteria['brand'] }}</option>
            @endif
            @foreach ($filters['brands'] as $option)
                <option value="{{ $option['slug'] }}" @selected($criteria['brand'] === $option['slug'])>{{ $option['name'] }}</option>
            @endforeach
        </select>
    </div>

    @unless (in_array('store', $omit, true))
        <div>
            <label for="{{ $idPrefix }}-store" class="mb-1.5 block text-caption font-medium text-ink">{{ __('Store') }}</label>
            <select id="{{ $idPrefix }}-store" name="store" class="w-full border-line bg-surface text-sm focus:border-brand focus:ring-brand">
                <option value="">{{ __('All stores') }}</option>
                @if ($criteria['store'] !== null && ! $hasOption($filters['stores'], 'slug', $criteria['store']))
                    <option value="{{ $criteria['store'] }}" selected>{{ $criteria['store'] }}</option>
                @endif
                @foreach ($filters['stores'] as $option)
                    <option value="{{ $option['slug'] }}" @selected($criteria['store'] === $option['slug'])>{{ $option['name'] }}</option>
                @endforeach
            </select>
        </div>
    @endunless

    <div>
        <label for="{{ $idPrefix }}-currency" class="mb-1.5 block text-caption font-medium text-ink">{{ __('Currency') }}</label>
        <select id="{{ $idPrefix }}-currency" name="currency" x-model="currency" @change="if (!currency) sort = 'newest'" class="w-full border-line bg-surface text-sm focus:border-brand focus:ring-brand">
            <option value="">{{ __('Any currency') }}</option>
            @if ($criteria['currency'] !== null && ! $hasOption($filters['currencies'], 'code', $criteria['currency']))
                <option value="{{ $criteria['currency'] }}" selected>{{ $criteria['currency'] }}</option>
            @endif
            @foreach ($filters['currencies'] as $option)
                <option value="{{ $option['code'] }}" @selected($criteria['currency'] === $option['code'])>{{ $option['label'] }}</option>
            @endforeach
        </select>
    </div>

    <fieldset :aria-disabled="(!currency).toString()">
        <legend class="mb-1.5 text-caption font-medium text-ink">{{ __('Price range') }}</legend>
        <div class="grid grid-cols-2 gap-2">
            <label>
                <span class="sr-only">{{ __('Minimum price') }}</span>
                <input type="text" inputmode="decimal" name="min_price" value="{{ $criteria['min_price'] }}" :disabled="!currency" class="w-full border-line bg-surface text-sm focus:border-brand focus:ring-brand disabled:cursor-not-allowed disabled:bg-canvas" placeholder="{{ __('Min') }}">
            </label>
            <label>
                <span class="sr-only">{{ __('Maximum price') }}</span>
                <input type="text" inputmode="decimal" name="max_price" value="{{ $criteria['max_price'] }}" :disabled="!currency" class="w-full border-line bg-surface text-sm focus:border-brand focus:ring-brand disabled:cursor-not-allowed disabled:bg-canvas" placeholder="{{ __('Max') }}">
            </label>
        </div>
        <p class="mt-1.5 text-caption text-ink-muted" x-show="!currency">{{ __('Choose a currency to use price filters and price sorting.') }}</p>
    </fieldset>

    <div>
        <label for="{{ $idPrefix }}-availability" class="mb-1.5 block text-caption font-medium text-ink">{{ __('Availability') }}</label>
        <select id="{{ $idPrefix }}-availability" name="availability" class="w-full border-line bg-surface text-sm focus:border-brand focus:ring-brand">
            <option value="any" @selected($criteria['availability'] === 'any')>{{ __('Any availability') }}</option>
            <option value="in_stock" @selected($criteria['availability'] === 'in_stock')>{{ __('In stock') }}</option>
        </select>
    </div>

    @foreach ($filters['attributes'] as $attribute)
        <fieldset class="border-t border-line pt-4">
            <legend class="text-caption font-medium text-ink">{{ $attribute['name'] }}</legend>
            <div class="mt-2 space-y-2">
                @foreach ($attribute['values'] as $value)
                    <label class="flex items-center gap-2 text-sm text-ink-muted">
                        <input
                            type="checkbox"
                            name="attrs[{{ $attribute['code'] }}][]"
                            value="{{ $value['code'] }}"
                            @checked(in_array($value['code'], $criteria['attrs'][$attribute['code']] ?? [], true))
                            class="border-line text-brand focus:ring-brand"
                        >
                        <span>{{ $value['name'] }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endforeach

    <div>
        <label for="{{ $idPrefix }}-sort" class="mb-1.5 block text-caption font-medium text-ink">{{ __('Sort by') }}</label>
        <select id="{{ $idPrefix }}-sort" name="sort" x-model="sort" class="w-full border-line bg-surface text-sm focus:border-brand focus:ring-brand">
            <option value="newest" @selected($criteria['sort'] === 'newest')>{{ __('Newest') }}</option>
            <option value="name" @selected($criteria['sort'] === 'name')>{{ __('Name') }}</option>
            <option value="price_asc" :disabled="!currency" @selected($criteria['sort'] === 'price_asc')>{{ __('Price: low to high') }}</option>
            <option value="price_desc" :disabled="!currency" @selected($criteria['sort'] === 'price_desc')>{{ __('Price: high to low') }}</option>
        </select>
    </div>

    <div class="grid grid-cols-2 gap-2 pt-2">
        <x-ui.button type="submit" class="w-full">{{ __('Apply filters') }}</x-ui.button>
        <x-ui.button :href="$clearUrl" variant="secondary" type="button" class="w-full">{{ __('Clear all') }}</x-ui.button>
    </div>
</form>
