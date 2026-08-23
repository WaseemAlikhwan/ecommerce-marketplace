<?php

namespace Database\Factories;

use App\Enums\VendorOrderStatus;
use App\Models\ParentOrder;
use App\Models\Store;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Support\PublicOrderCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorOrder>
 */
class VendorOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $itemsSubtotal = fake()->numberBetween(1_000, 500_000);
        $shipping = fake()->numberBetween(0, 50_000);
        $rateBps = 1000;
        $commissionAmount = intdiv($itemsSubtotal * $rateBps, 10_000);

        return [
            'public_code' => PublicOrderCode::vendor(),
            'parent_order_id' => ParentOrder::factory(),
            'vendor_id' => Vendor::factory(),
            'store_id' => Store::factory(),
            'store_name' => fake()->company(),
            'currency_code' => 'SYP',
            'status' => VendorOrderStatus::Pending,
            'items_subtotal_amount_minor' => $itemsSubtotal,
            'shipping_amount_minor' => $shipping,
            'grand_total_amount_minor' => $itemsSubtotal + $shipping,
            'commission_rate_bps' => $rateBps,
            'commission_base_amount_minor' => $itemsSubtotal,
            'commission_amount_minor' => $commissionAmount,
            'commission_recognized_at' => null,
            'shipping_recipient_name' => fake()->name(),
            'shipping_phone' => '+9639'.fake()->numerify('########'),
            'shipping_governorate_id' => null,
            'shipping_city_id' => null,
            'shipping_governorate_name_ar' => 'دمشق',
            'shipping_governorate_name_en' => 'Damascus',
            'shipping_city_name_ar' => 'دمشق',
            'shipping_city_name_en' => 'Damascus',
            'shipping_country_code' => 'SY',
            'shipping_line1' => fake()->streetAddress(),
            'shipping_line2' => null,
            'shipping_notes' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (VendorOrder $order): void {
            $store = $order->store;
            if ($store === null) {
                return;
            }

            $order->forceFill([
                'vendor_id' => $store->vendor_id,
                'store_name' => $store->name,
                'currency_code' => $store->default_currency_code ?: 'SYP',
            ]);

            $parent = $order->parentOrder;
            if ($parent !== null) {
                $order->forceFill([
                    'shipping_recipient_name' => $parent->shipping_recipient_name,
                    'shipping_phone' => $parent->shipping_phone,
                    'shipping_governorate_id' => $parent->shipping_governorate_id,
                    'shipping_city_id' => $parent->shipping_city_id,
                    'shipping_governorate_name_ar' => $parent->shipping_governorate_name_ar,
                    'shipping_governorate_name_en' => $parent->shipping_governorate_name_en,
                    'shipping_city_name_ar' => $parent->shipping_city_name_ar,
                    'shipping_city_name_en' => $parent->shipping_city_name_en,
                    'shipping_country_code' => $parent->shipping_country_code,
                    'shipping_line1' => $parent->shipping_line1,
                    'shipping_line2' => $parent->shipping_line2,
                    'shipping_notes' => $parent->shipping_notes,
                ]);
            }

            $order->save();
        });
    }

    public function forStore(Store $store): static
    {
        return $this->state(fn (array $attributes) => [
            'vendor_id' => $store->vendor_id,
            'store_id' => $store->id,
            'store_name' => $store->name,
            'currency_code' => $store->default_currency_code ?: 'SYP',
        ]);
    }
}
