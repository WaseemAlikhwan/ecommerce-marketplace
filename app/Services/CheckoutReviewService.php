<?php

namespace App\Services;

use App\Cart\CartViewLine;
use App\Checkout\CheckoutReview;
use App\Contracts\ShippingCalculator;
use App\Models\Currency;
use App\Models\CustomerAddress;
use App\Models\Governorate;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Support\CheckedInteger;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Builds checkout review presentation (queries here; Blade stays query-free).
 */
class CheckoutReviewService
{
    public function __construct(
        private readonly CartViewService $cartViews,
        private readonly ShippingCalculator $shippingCalculator,
    ) {}

    public function review(User $user, ?string $locale = null): CheckoutReview
    {
        $locale = $locale ?: app()->getLocale();
        $cart = $this->cartViews->view($user, $locale);
        $payableLines = array_values(array_filter(
            $cart->lines,
            static fn (CartViewLine $line): bool => $line->contributesToTotals(),
        ));

        $variantIds = array_map(static fn (CartViewLine $line): int => $line->variantId, $payableLines);

        /** @var Collection<int, ProductVariant> $variants */
        $variants = $variantIds === []
            ? collect()
            : ProductVariant::query()
                ->with(['product.store.vendor'])
                ->whereIn('id', $variantIds)
                ->get()
                ->keyBy('id');

        /** @var array<int, array{store: Store, vendor: Vendor, currency_code: string, items_subtotal_minor: int, exponent: int}> $groups */
        $groups = [];

        foreach ($payableLines as $line) {
            $variant = $variants->get($line->variantId);
            $store = $variant?->product?->store;
            $vendor = $store?->vendor;
            if ($variant === null || $store === null || $vendor === null) {
                continue;
            }

            $vendorId = (int) $vendor->id;
            $lineMinor = (int) ($line->lineTotal['amount_minor'] ?? 0);

            if (! isset($groups[$vendorId])) {
                $groups[$vendorId] = [
                    'store' => $store,
                    'vendor' => $vendor,
                    'currency_code' => $line->currencyCode,
                    'items_subtotal_minor' => 0,
                    'exponent' => $line->currencyExponent,
                ];
            }

            $groups[$vendorId]['items_subtotal_minor'] = CheckedInteger::add(
                $groups[$vendorId]['items_subtotal_minor'],
                $lineMinor,
            );
        }

        $exponents = Currency::query()
            ->whereIn('code', collect($groups)->pluck('currency_code')->unique()->filter()->values()->all())
            ->pluck('exponent', 'code');

        $vendorGroups = [];
        /** @var array<string, int> $duesMinor */
        $duesMinor = [];

        foreach ($groups as $group) {
            $currency = $group['currency_code'];
            $exponent = (int) ($exponents[$currency] ?? $group['exponent']);
            $shippingMinor = $this->shippingCalculator->feeForVendorOrder(
                $group['vendor'],
                $group['store'],
                $currency,
            );
            $dueMinor = CheckedInteger::add($group['items_subtotal_minor'], $shippingMinor);
            $duesMinor[$currency] = CheckedInteger::add($duesMinor[$currency] ?? 0, $dueMinor);

            $vendorGroups[] = [
                'store_name' => (string) $group['store']->name,
                'currency_code' => $currency,
                'items_subtotal' => $this->moneyPayload($currency, $exponent, $group['items_subtotal_minor']),
                'shipping' => $this->moneyPayload($currency, $exponent, $shippingMinor),
                'due' => $this->moneyPayload($currency, $exponent, $dueMinor),
            ];
        }

        ksort($duesMinor);
        $codDues = [];
        foreach ($duesMinor as $code => $minor) {
            $codDues[] = $this->moneyPayload($code, (int) ($exponents[$code] ?? 0), $minor);
        }

        $addresses = CustomerAddress::query()
            ->with(['governorate', 'city'])
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(function (CustomerAddress $address) use ($locale): array {
                $gov = $locale === 'en'
                    ? (string) ($address->governorate?->name_en ?? '')
                    : (string) ($address->governorate?->name_ar ?? '');
                $city = $locale === 'en'
                    ? (string) ($address->city?->name_en ?? '')
                    : (string) ($address->city?->name_ar ?? '');

                return [
                    'id' => (int) $address->id,
                    'label' => (string) ($address->label ?: __('Address')),
                    'recipient_name' => (string) $address->recipient_name,
                    'phone' => (string) $address->phone,
                    'summary' => trim($address->line1.($address->line2 ? ', '.$address->line2 : '').' — '.$city.', '.$gov),
                    'is_default' => (bool) $address->is_default,
                ];
            })
            ->values()
            ->all();

        $defaultAddressId = null;
        foreach ($addresses as $address) {
            if ($address['is_default']) {
                $defaultAddressId = $address['id'];
                break;
            }
        }
        if ($defaultAddressId === null && $addresses !== []) {
            $defaultAddressId = $addresses[0]['id'];
        }

        $governorates = Governorate::query()
            ->with(['cities' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
            ->inSyria()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (Governorate $governorate) use ($locale): array {
                return [
                    'id' => (int) $governorate->id,
                    'name' => $locale === 'en' ? $governorate->name_en : $governorate->name_ar,
                    'cities' => $governorate->cities->map(fn ($city): array => [
                        'id' => (int) $city->id,
                        'name' => $locale === 'en' ? $city->name_en : $city->name_ar,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();

        return new CheckoutReview(
            lines: $payableLines,
            vendorGroups: $vendorGroups,
            codDues: $codDues,
            addresses: $addresses,
            governorates: $governorates,
            hasPayableLines: $payableLines !== [],
            defaultAddressId: $defaultAddressId,
        );
    }

    /**
     * @return array{currency_code: string, exponent: int, amount_minor: string, label: string}
     */
    private function moneyPayload(string $code, int $exponent, int $minor): array
    {
        return [
            'currency_code' => $code,
            'exponent' => $exponent,
            'amount_minor' => (string) $minor,
            'label' => Money::formatFromMinor($minor, $exponent).' '.$code,
        ];
    }
}
