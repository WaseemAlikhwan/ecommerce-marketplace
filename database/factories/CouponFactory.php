<?php

namespace Database\Factories;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SAVE##??')),
            'scope' => CouponScope::Platform,
            'vendor_id' => null,
            'type' => CouponType::Percent,
            'value' => 10,
            'currency_code' => 'SYP',
            'starts_at' => null,
            'ends_at' => null,
            'min_eligible_amount_minor' => 0,
            'max_discount_amount_minor' => null,
            'global_usage_limit' => null,
            'per_user_usage_limit' => null,
            'is_active' => true,
        ];
    }

    public function platform(): static
    {
        return $this->state(fn () => [
            'scope' => CouponScope::Platform,
            'vendor_id' => null,
        ]);
    }

    public function forVendor(?Vendor $vendor = null): static
    {
        return $this->state(fn () => [
            'scope' => CouponScope::Vendor,
            'vendor_id' => $vendor?->id ?? Vendor::factory(),
        ]);
    }

    public function percent(int $percent): static
    {
        return $this->state(fn () => [
            'type' => CouponType::Percent,
            'value' => $percent,
        ]);
    }

    public function fixed(int $amountMinor): static
    {
        return $this->state(fn () => [
            'type' => CouponType::Fixed,
            'value' => $amountMinor,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
