<?php

namespace Database\Factories;

use App\Enums\CouponRedemptionStatus;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CouponRedemption>
 */
class CouponRedemptionFactory extends Factory
{
    protected $model = CouponRedemption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coupon_id' => Coupon::factory(),
            'user_id' => User::factory(),
            'parent_order_id' => null,
            'vendor_order_id' => null,
            'discount_amount_minor' => 100,
            'currency_code' => 'SYP',
            'status' => CouponRedemptionStatus::Active,
        ];
    }

    public function released(): static
    {
        return $this->state(fn () => ['status' => CouponRedemptionStatus::Released]);
    }
}
