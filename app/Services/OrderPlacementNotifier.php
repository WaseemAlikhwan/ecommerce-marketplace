<?php

namespace App\Services;

use App\Checkout\PlaceOrderResult;
use App\Models\Currency;
use App\Models\User;
use App\Models\VendorOrder;
use App\Notifications\OrderPlacedCustomerNotification;
use App\Notifications\VendorOrderReceivedNotification;
use App\Support\Money;

/**
 * Dispatches customer + vendor notifications after successful checkout.
 */
class OrderPlacementNotifier
{
    public function notify(User $customer, PlaceOrderResult $result): void
    {
        $parent = $result->parentOrder->loadMissing([
            'vendorOrders.vendor.user',
            'vendorOrders.currency',
        ]);

        $exponents = Currency::query()
            ->whereIn('code', array_keys($result->codDuesMinorByCurrency))
            ->pluck('exponent', 'code');

        $codLabels = [];
        foreach ($result->codDuesMinorByCurrency as $code => $minor) {
            $exponent = (int) ($exponents[$code] ?? 0);
            $codLabels[] = Money::formatFromMinor((int) $minor, $exponent).' '.$code;
        }

        $customer->notify(new OrderPlacedCustomerNotification($parent, $codLabels));

        foreach ($parent->vendorOrders as $vendorOrder) {
            $vendorUser = $vendorOrder->vendor?->user;
            if ($vendorUser === null) {
                continue;
            }

            $vendorUser->notify(new VendorOrderReceivedNotification(
                $vendorOrder,
                $this->grandTotalLabel($vendorOrder),
            ));
        }
    }

    private function grandTotalLabel(VendorOrder $vendorOrder): string
    {
        $currency = (string) $vendorOrder->currency_code;
        $exponent = (int) ($vendorOrder->currency?->exponent
            ?? Currency::query()->where('code', $currency)->value('exponent')
            ?? 0);

        return Money::formatFromMinor((int) $vendorOrder->grand_total_amount_minor, $exponent).' '.$currency;
    }
}
