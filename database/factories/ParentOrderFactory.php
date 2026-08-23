<?php

namespace Database\Factories;

use App\Enums\ParentOrderStatus;
use App\Models\City;
use App\Models\Governorate;
use App\Models\ParentOrder;
use App\Models\User;
use App\Support\PublicOrderCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParentOrder>
 */
class ParentOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $governorate = Governorate::query()->inRandomOrder()->first()
            ?? Governorate::factory()->create();
        $city = City::query()->where('governorate_id', $governorate->id)->inRandomOrder()->first()
            ?? City::factory()->for($governorate)->create();

        return [
            'public_code' => PublicOrderCode::parent(),
            'user_id' => User::factory(),
            'status' => ParentOrderStatus::Placed,
            'shipping_recipient_name' => fake()->name(),
            'shipping_phone' => '+9639'.fake()->numerify('########'),
            'shipping_governorate_id' => $governorate->id,
            'shipping_city_id' => $city->id,
            'shipping_governorate_name_ar' => $governorate->name_ar,
            'shipping_governorate_name_en' => $governorate->name_en,
            'shipping_city_name_ar' => $city->name_ar,
            'shipping_city_name_en' => $city->name_en,
            'shipping_country_code' => 'SY',
            'shipping_line1' => fake()->streetAddress(),
            'shipping_line2' => null,
            'shipping_notes' => null,
            'placed_at' => now(),
        ];
    }
}
