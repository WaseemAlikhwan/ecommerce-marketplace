@php
    $criteria = $catalog['criteria'];
    $omit = $omit ?? [];
    $chips = [];

    $optionName = static function (array $options, string $key, ?string $value): ?string {
        if ($value === null) {
            return null;
        }
        foreach ($options as $option) {
            if (($option[$key] ?? null) === $value) {
                return $option['name'] ?? $option['label'] ?? $value;
            }
        }
        return $value;
    };

    $addChip = static function (string $label, string $key, ?string $attributeCode = null, ?string $valueCode = null) use (&$chips, $catalog, $action): void {
        $next = $catalog['query'];
        if ($key === 'attrs' && $attributeCode !== null && $valueCode !== null) {
            $values = array_values(array_filter(
                $next['attrs'][$attributeCode] ?? [],
                static fn (string $value): bool => $value !== $valueCode,
            ));
            if ($values === []) {
                unset($next['attrs'][$attributeCode]);
            } else {
                $next['attrs'][$attributeCode] = $values;
            }
            if (($next['attrs'] ?? []) === []) {
                unset($next['attrs']);
            }
        } elseif ($key === 'currency') {
            unset($next['currency'], $next['min_price'], $next['max_price']);
            if (in_array($next['sort'] ?? null, ['price_asc', 'price_desc'], true)) {
                unset($next['sort']);
            }
        } else {
            unset($next[$key]);
        }

        $chips[] = [
            'label' => $label,
            'url' => $next === [] ? $action : $action.'?'.http_build_query($next, '', '&', PHP_QUERY_RFC3986),
        ];
    };

    if ($criteria['q'] !== null) {
        $addChip(__('Search: :value', ['value' => $criteria['q']]), 'q');
    }
    if ($criteria['category'] !== null && ! in_array('category', $omit, true)) {
        $addChip($optionName($filters['categories'], 'slug', $criteria['category']) ?? $criteria['category'], 'category');
    }
    if ($criteria['brand'] !== null) {
        $addChip($optionName($filters['brands'], 'slug', $criteria['brand']) ?? $criteria['brand'], 'brand');
    }
    if ($criteria['store'] !== null && ! in_array('store', $omit, true)) {
        $addChip($optionName($filters['stores'], 'slug', $criteria['store']) ?? $criteria['store'], 'store');
    }
    if ($criteria['currency'] !== null) {
        $addChip($optionName($filters['currencies'], 'code', $criteria['currency']) ?? $criteria['currency'], 'currency');
    }
    if ($criteria['min_price'] !== null) {
        $addChip(__('Min: :value', ['value' => $criteria['min_price']]), 'min_price');
    }
    if ($criteria['max_price'] !== null) {
        $addChip(__('Max: :value', ['value' => $criteria['max_price']]), 'max_price');
    }
    if ($criteria['availability'] === 'in_stock') {
        $addChip(__('In stock'), 'availability');
    }
    if ($criteria['sort'] !== 'newest') {
        $sortLabels = [
            'name' => __('Name'),
            'price_asc' => __('Price: low to high'),
            'price_desc' => __('Price: high to low'),
        ];
        $addChip($sortLabels[$criteria['sort']] ?? $criteria['sort'], 'sort');
    }

    foreach ($criteria['attrs'] as $attributeCode => $valueCodes) {
        $attribute = null;
        foreach ($filters['attributes'] as $candidate) {
            if ($candidate['code'] === $attributeCode) {
                $attribute = $candidate;
                break;
            }
        }
        foreach ($valueCodes as $valueCode) {
            $valueName = $attribute ? $optionName($attribute['values'], 'code', $valueCode) : $valueCode;
            $addChip(($attribute['name'] ?? $attributeCode).': '.$valueName, 'attrs', $attributeCode, $valueCode);
        }
    }

    $hasUnresolvedIssues = collect($catalog['issues'])->contains(
        static fn (array $issue): bool => $issue['kind'] === 'unresolved',
    );
@endphp

@if ($catalog['issues'] !== [])
    <div
        class="mb-6 border-s-2 {{ $hasUnresolvedIssues ? 'border-danger bg-danger/5' : 'border-accent bg-accent/5' }} px-4 py-3"
        role="{{ $hasUnresolvedIssues ? 'alert' : 'status' }}"
        aria-labelledby="catalog-issue-summary"
    >
        <h2 id="catalog-issue-summary" class="text-sm font-semibold text-ink">{{ __('Some filters need attention') }}</h2>
        <ul class="mt-1.5 list-disc space-y-1 ps-5 text-sm text-ink">
            @foreach ($catalog['issues'] as $issue)
                <li>{{ $issue['message'] }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if ($chips !== [])
    <div class="mb-6 flex flex-wrap items-center gap-2" aria-label="{{ __('Active filters') }}">
        @foreach ($chips as $chip)
            <a href="{{ $chip['url'] }}" class="inline-flex items-center gap-2 border border-line bg-surface px-3 py-1.5 text-caption text-ink transition hover:border-ink/30">
                <span>{{ $chip['label'] }}</span>
                <span aria-hidden="true">×</span>
                <span class="sr-only">{{ __('Remove filter') }}</span>
            </a>
        @endforeach
        <a href="{{ $clearUrl }}" class="px-2 py-1.5 text-caption text-ink-muted underline-offset-4 hover:text-ink hover:underline">{{ __('Clear all') }}</a>
    </div>
@endif
