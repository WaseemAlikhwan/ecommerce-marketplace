<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\VendorOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_order_id' => VendorOrder::factory(),
            'method' => PaymentMethod::Cod,
            'status' => PaymentStatus::Pending,
            'currency_code' => 'SYP',
            'amount_minor' => 0,
            'collected_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Payment $payment): void {
            if ($payment->amount_minor !== 0) {
                return;
            }

            $vendorOrder = $payment->vendorOrder;
            if ($vendorOrder === null && $payment->vendor_order_id) {
                $vendorOrder = VendorOrder::query()->find($payment->vendor_order_id);
            }

            if ($vendorOrder === null) {
                return;
            }

            $payment->amount_minor = $vendorOrder->grand_total_amount_minor;
            $payment->currency_code = $vendorOrder->currency_code;
        });
    }
}
