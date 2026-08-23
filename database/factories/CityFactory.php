<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Governorate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nameEn = fake()->unique()->city();

        return [
            'governorate_id' => Governorate::factory(),
            'code' => Str::slug($nameEn).'-'.fake()->unique()->numerify('###'),
            'name_ar' => 'مدينة '.$nameEn,
            'name_en' => $nameEn,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
