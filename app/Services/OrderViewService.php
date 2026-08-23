<?php

namespace App\Services;

use App\Checkout\ParentOrderView;
use App\Checkout\VendorOrderView;
use App\Models\Currency;
use App\Models\OrderItem;
use App\Models\ParentOrder;
use App\Models\Payment;
use App\Models\VendorOrder;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Builds query-free order presentation for Blade (queries stay here).
 */
class OrderViewService
{
    public function parent(ParentOrder $parentOrder, ?string $locale = null): ParentOrderView
    {
        $locale = $locale ?: app()->getLocale();

        $parentOrder->loadMissing([
            'vendorOrders.payment',
            'vendorOrders.items',
            'vendorOrders.currency',
        ]);

        $exponents = $this->exponentsFor(
            $parentOrder->vendorOrders->pluck('currency_code')->filter()->unique()->values()->all()
        );

        /** @var array<string, int> $duesMinor */
        $duesMinor = [];
        $vendorOrders = [];

        foreach ($parentOrder->vendorOrders->sortBy('id') as $vendorOrder) {
            $currency = (string) $vendorOrder->currency_code;
            $exponent = (int) ($exponents[$currency] ?? 0);
            $payment = $vendorOrder->payment;
            $dueMinor = (int) ($payment?->amount_minor ?? $vendorOrder->grand_total_amount_minor);
            $duesMinor[$currency] = ($duesMinor[$currency] ?? 0) + $dueMinor;

            $vendorOrders[] = [
                'public_code' => (string) $vendorOrder->public_code,
                'store_name' => (string) $vendorOrder->store_name,
                'status' => $this->vendorStatusLabel($vendorOrder),
                'currency_code' => $currency,
                'items_subtotal_label' => $this->moneyLabel($vendorOrder->items_subtotal_amount_minor, $currency, $exponent),
                'shipping_label' => $this->moneyLabel($vendorOrder->shipping_amount_minor, $currency, $exponent),
                'grand_total_label' => $this->moneyLabel($vendorOrder->grand_total_amount_minor, $currency, $exponent),
                'payment_status' => $this->paymentStatusLabel($payment),
                'items' => $vendorOrder->items->sortBy('id')->map(
                    fn (OrderItem $item): array => $this->itemPayload($item, $locale, $exponent)
                )->values()->all(),
            ];
        }

        ksort($duesMinor);
        $codDues = [];
        foreach ($duesMinor as $code => $minor) {
            $codDues[] = [
                'currency_code' => $code,
                'label' => $this->moneyLabel($minor, $code, (int) ($exponents[$code] ?? 0)),
            ];
        }

        return new ParentOrderView(
            id: (int) $parentOrder->id,
            publicCode: (string) $parentOrder->public_code,
            status: $this->parentStatusLabel($parentOrder),
            placedAtLabel: $parentOrder->placed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—',
            shipping: $this->shippingPayload($parentOrder, $locale),
            vendorOrders: $vendorOrders,
            codDues: $codDues,
        );
    }

    public function vendor(VendorOrder $vendorOrder, ?string $locale = null): VendorOrderView
    {
        $locale = $locale ?: app()->getLocale();

        $vendorOrder->loadMissing(['items', 'payment', 'parentOrder', 'currency']);

        $currency = (string) $vendorOrder->currency_code;
        $exponent = (int) ($vendorOrder->currency?->exponent
            ?? Currency::query()->where('code', $currency)->value('exponent')
            ?? 0);

        $payment = $vendorOrder->payment;
        $parent = $vendorOrder->parentOrder;

        return new VendorOrderView(
            id: (int) $vendorOrder->id,
            publicCode: (string) $vendorOrder->public_code,
            parentPublicCode: (string) ($parent?->public_code ?? ''),
            status: $this->vendorStatusLabel($vendorOrder),
            currencyCode: $currency,
            itemsSubtotalLabel: $this->moneyLabel($vendorOrder->items_subtotal_amount_minor, $currency, $exponent),
            shippingLabel: $this->moneyLabel($vendorOrder->shipping_amount_minor, $currency, $exponent),
            grandTotalLabel: $this->moneyLabel($vendorOrder->grand_total_amount_minor, $currency, $exponent),
            paymentStatus: $this->paymentStatusLabel($payment),
            paymentMethod: $payment?->method?->value === 'cod' ? __('Cash on delivery') : __('Payment'),
            shipping: $this->shippingPayload($vendorOrder, $locale),
            items: $vendorOrder->items->sortBy('id')->map(
                fn (OrderItem $item): array => [
                    'name' => $this->itemName($item, $locale),
                    'quantity' => (int) $item->quantity,
                    'unit_price_label' => $this->moneyLabel($item->unit_price_amount_minor, $currency, $exponent),
                    'line_total_label' => $this->moneyLabel($item->line_total_amount_minor, $currency, $exponent),
                ]
            )->values()->all(),
            placedAtLabel: $parent?->placed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—',
        );
    }

    /**
     * @param  Collection<int, ParentOrder>  $orders
     * @return list<array{id: int, public_code: string, status: string, placed_at_label: string, vendor_count: int, cod_dues: list<string>}>
     */
    public function parentIndexRows(Collection $orders, ?string $locale = null): array
    {
        $locale = $locale ?: app()->getLocale();

        $orders->loadMissing(['vendorOrders.payment']);

        $codes = $orders->flatMap(fn (ParentOrder $o) => $o->vendorOrders->pluck('currency_code'))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $exponents = $this->exponentsFor($codes);

        return $orders->map(function (ParentOrder $order) use ($exponents): array {
            /** @var array<string, int> $dues */
            $dues = [];
            foreach ($order->vendorOrders as $vendorOrder) {
                $code = (string) $vendorOrder->currency_code;
                $minor = (int) ($vendorOrder->payment?->amount_minor ?? $vendorOrder->grand_total_amount_minor);
                $dues[$code] = ($dues[$code] ?? 0) + $minor;
            }
            ksort($dues);
            $labels = [];
            foreach ($dues as $code => $minor) {
                $labels[] = $this->moneyLabel($minor, $code, (int) ($exponents[$code] ?? 0));
            }

            return [
                'id' => (int) $order->id,
                'public_code' => (string) $order->public_code,
                'status' => $this->parentStatusLabel($order),
                'placed_at_label' => $order->placed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—',
                'vendor_count' => $order->vendorOrders->count(),
                'cod_dues' => $labels,
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, VendorOrder>  $orders
     * @return list<array{id: int, public_code: string, status: string, placed_at_label: string, grand_total_label: string, payment_status: string, currency_code: string}>
     */
    public function vendorIndexRows(Collection $orders, ?string $locale = null): array
    {
        $orders->loadMissing(['payment', 'parentOrder', 'currency']);

        return $orders->map(function (VendorOrder $order): array {
            $currency = (string) $order->currency_code;
            $exponent = (int) ($order->currency?->exponent ?? 0);

            return [
                'id' => (int) $order->id,
                'public_code' => (string) $order->public_code,
                'status' => $this->vendorStatusLabel($order),
                'placed_at_label' => $order->parentOrder?->placed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—',
                'grand_total_label' => $this->moneyLabel($order->grand_total_amount_minor, $currency, $exponent),
                'payment_status' => $this->paymentStatusLabel($order->payment),
                'currency_code' => $currency,
            ];
        })->values()->all();
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, int>
     */
    private function exponentsFor(array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        return Currency::query()
            ->whereIn('code', $codes)
            ->pluck('exponent', 'code')
            ->map(fn ($e) => (int) $e)
            ->all();
    }

    /**
     * @return array{recipient_name: string, phone: string, lines: string, locality: string, country_code: string, notes: ?string}
     */
    private function shippingPayload(ParentOrder|VendorOrder $order, string $locale): array
    {
        $gov = $locale === 'en'
            ? (string) $order->shipping_governorate_name_en
            : (string) $order->shipping_governorate_name_ar;
        $city = $locale === 'en'
            ? (string) $order->shipping_city_name_en
            : (string) $order->shipping_city_name_ar;

        $lines = trim((string) $order->shipping_line1);
        if ($order->shipping_line2) {
            $lines .= ($lines !== '' ? ', ' : '').$order->shipping_line2;
        }

        return [
            'recipient_name' => (string) $order->shipping_recipient_name,
            'phone' => (string) $order->shipping_phone,
            'lines' => $lines,
            'locality' => trim($city.($gov !== '' ? ', '.$gov : '')),
            'country_code' => (string) $order->shipping_country_code,
            'notes' => $order->shipping_notes ? (string) $order->shipping_notes : null,
        ];
    }

    /**
     * @return array{name: string, quantity: int, line_total_label: string}
     */
    private function itemPayload(OrderItem $item, string $locale, int $exponent): array
    {
        return [
            'name' => $this->itemName($item, $locale),
            'quantity' => (int) $item->quantity,
            'line_total_label' => $this->moneyLabel(
                $item->line_total_amount_minor,
                (string) $item->currency_code,
                $exponent,
            ),
        ];
    }

    private function itemName(OrderItem $item, string $locale): string
    {
        return $locale === 'en'
            ? (string) ($item->product_name_en ?: $item->product_name_ar)
            : (string) ($item->product_name_ar ?: $item->product_name_en);
    }

    private function moneyLabel(int $minor, string $code, int $exponent): string
    {
        return Money::formatFromMinor($minor, $exponent).' '.$code;
    }

    private function parentStatusLabel(ParentOrder $order): string
    {
        return match ($order->status->value) {
            'placed' => __('Placed'),
            'cancelled' => __('Cancelled'),
            default => __(ucfirst($order->status->value)),
        };
    }

    private function vendorStatusLabel(VendorOrder $order): string
    {
        return match ($order->status->value) {
            'pending' => __('Pending'),
            'confirmed' => __('Confirmed'),
            'processing' => __('Processing'),
            'shipped' => __('Shipped'),
            'delivered' => __('Delivered'),
            'cancelled' => __('Cancelled'),
            default => __(ucfirst($order->status->value)),
        };
    }

    private function paymentStatusLabel(?Payment $payment): string
    {
        if ($payment === null) {
            return __('Payment pending');
        }

        return match ($payment->status->value) {
            'pending' => __('COD pending'),
            'collected' => __('Collected'),
            'cancelled' => __('Cancelled'),
            default => __(ucfirst($payment->status->value)),
        };
    }
}
