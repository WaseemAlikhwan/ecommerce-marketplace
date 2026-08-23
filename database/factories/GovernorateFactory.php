<?php

namespace Database\Factories;

use App\Models\Governorate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Governorate>
 */
class GovernorateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nameEn = fake()->unique()->city();

        return [
            'code' => Str::slug($nameEn).'-'.fake()->unique()->numerify('###'),
            'name_ar' => 'محافظة '.$nameEn,
            'name_en' => $nameEn,
            'country_code' => 'SY',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
