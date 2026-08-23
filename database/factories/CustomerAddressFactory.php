<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\CustomerAddress;
use App\Models\Governorate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
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
            'user_id' => User::factory(),
            'label' => fake()->optional()->randomElement(['Home', 'Work', 'المنزل', 'العمل']),
            'recipient_name' => fake()->name(),
            'phone' => '+9639'.fake()->numerify('########'),
            'governorate_id' => $governorate->id,
            'city_id' => $city->id,
            'line1' => fake()->streetAddress(),
            'line2' => fake()->optional()->secondaryAddress(),
            'notes' => fake()->optional()->sentence(),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
