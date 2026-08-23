<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Governorate;
use Illuminate\Database\Seeder;

class SyriaGeoSeeder extends Seeder
{
    public function run(): void
    {
        $governorates = [
            ['code' => 'damascus', 'name_ar' => 'دمشق', 'name_en' => 'Damascus', 'sort_order' => 1, 'cities' => [
                ['code' => 'damascus-city', 'name_ar' => 'دمشق', 'name_en' => 'Damascus', 'sort_order' => 1],
            ]],
            ['code' => 'rif-dimashq', 'name_ar' => 'ريف دمشق', 'name_en' => 'Rif Dimashq', 'sort_order' => 2, 'cities' => [
                ['code' => 'douma', 'name_ar' => 'دوما', 'name_en' => 'Douma', 'sort_order' => 1],
                ['code' => 'darayya', 'name_ar' => 'داريا', 'name_en' => 'Darayya', 'sort_order' => 2],
                ['code' => 'yabroud', 'name_ar' => 'يبرود', 'name_en' => 'Yabroud', 'sort_order' => 3],
            ]],
            ['code' => 'aleppo', 'name_ar' => 'حلب', 'name_en' => 'Aleppo', 'sort_order' => 3, 'cities' => [
                ['code' => 'aleppo-city', 'name_ar' => 'حلب', 'name_en' => 'Aleppo', 'sort_order' => 1],
                ['code' => 'manbij', 'name_ar' => 'منبج', 'name_en' => 'Manbij', 'sort_order' => 2],
                ['code' => 'al-bab', 'name_ar' => 'الباب', 'name_en' => 'Al-Bab', 'sort_order' => 3],
            ]],
            ['code' => 'homs', 'name_ar' => 'حمص', 'name_en' => 'Homs', 'sort_order' => 4, 'cities' => [
                ['code' => 'homs-city', 'name_ar' => 'حمص', 'name_en' => 'Homs', 'sort_order' => 1],
                ['code' => 'palmyra', 'name_ar' => 'تدمر', 'name_en' => 'Palmyra', 'sort_order' => 2],
            ]],
            ['code' => 'hama', 'name_ar' => 'حماة', 'name_en' => 'Hama', 'sort_order' => 5, 'cities' => [
                ['code' => 'hama-city', 'name_ar' => 'حماة', 'name_en' => 'Hama', 'sort_order' => 1],
                ['code' => 'salamiyah', 'name_ar' => 'سلمية', 'name_en' => 'Salamiyah', 'sort_order' => 2],
            ]],
            ['code' => 'latakia', 'name_ar' => 'اللاذقية', 'name_en' => 'Latakia', 'sort_order' => 6, 'cities' => [
                ['code' => 'latakia-city', 'name_ar' => 'اللاذقية', 'name_en' => 'Latakia', 'sort_order' => 1],
                ['code' => 'jableh', 'name_ar' => 'جبلة', 'name_en' => 'Jableh', 'sort_order' => 2],
            ]],
            ['code' => 'tartus', 'name_ar' => 'طرطوس', 'name_en' => 'Tartus', 'sort_order' => 7, 'cities' => [
                ['code' => 'tartus-city', 'name_ar' => 'طرطوس', 'name_en' => 'Tartus', 'sort_order' => 1],
                ['code' => 'banias', 'name_ar' => 'بانياس', 'name_en' => 'Banias', 'sort_order' => 2],
            ]],
            ['code' => 'idlib', 'name_ar' => 'إدلب', 'name_en' => 'Idlib', 'sort_order' => 8, 'cities' => [
                ['code' => 'idlib-city', 'name_ar' => 'إدلب', 'name_en' => 'Idlib', 'sort_order' => 1],
                ['code' => 'ariha', 'name_ar' => 'أريحا', 'name_en' => 'Ariha', 'sort_order' => 2],
            ]],
            ['code' => 'deir-ez-zor', 'name_ar' => 'دير الزور', 'name_en' => 'Deir ez-Zor', 'sort_order' => 9, 'cities' => [
                ['code' => 'deir-ez-zor-city', 'name_ar' => 'دير الزور', 'name_en' => 'Deir ez-Zor', 'sort_order' => 1],
                ['code' => 'al-mayadin', 'name_ar' => 'الميادين', 'name_en' => 'Al-Mayadin', 'sort_order' => 2],
            ]],
            ['code' => 'raqqa', 'name_ar' => 'الرقة', 'name_en' => 'Raqqa', 'sort_order' => 10, 'cities' => [
                ['code' => 'raqqa-city', 'name_ar' => 'الرقة', 'name_en' => 'Raqqa', 'sort_order' => 1],
                ['code' => 'tabqa', 'name_ar' => 'الثورة', 'name_en' => 'Tabqa', 'sort_order' => 2],
            ]],
            ['code' => 'hasakah', 'name_ar' => 'الحسكة', 'name_en' => 'Hasakah', 'sort_order' => 11, 'cities' => [
                ['code' => 'hasakah-city', 'name_ar' => 'الحسكة', 'name_en' => 'Hasakah', 'sort_order' => 1],
                ['code' => 'qamishli', 'name_ar' => 'القامشلي', 'name_en' => 'Qamishli', 'sort_order' => 2],
            ]],
            ['code' => 'daraa', 'name_ar' => 'درعا', 'name_en' => 'Daraa', 'sort_order' => 12, 'cities' => [
                ['code' => 'daraa-city', 'name_ar' => 'درعا', 'name_en' => 'Daraa', 'sort_order' => 1],
                ['code' => 'izra', 'name_ar' => 'إزرع', 'name_en' => 'Izra', 'sort_order' => 2],
            ]],
            ['code' => 'as-suwayda', 'name_ar' => 'السويداء', 'name_en' => 'As-Suwayda', 'sort_order' => 13, 'cities' => [
                ['code' => 'as-suwayda-city', 'name_ar' => 'السويداء', 'name_en' => 'As-Suwayda', 'sort_order' => 1],
                ['code' => 'shahba', 'name_ar' => 'شهبا', 'name_en' => 'Shahba', 'sort_order' => 2],
            ]],
            ['code' => 'quneitra', 'name_ar' => 'القنيطرة', 'name_en' => 'Quneitra', 'sort_order' => 14, 'cities' => [
                ['code' => 'quneitra-city', 'name_ar' => 'القنيطرة', 'name_en' => 'Quneitra', 'sort_order' => 1],
            ]],
        ];

        foreach ($governorates as $payload) {
            $cities = $payload['cities'];
            unset($payload['cities']);

            $governorate = Governorate::query()->updateOrCreate(
                ['code' => $payload['code']],
                [
                    ...$payload,
                    'country_code' => 'SY',
                    'is_active' => true,
                ],
            );

            foreach ($cities as $cityPayload) {
                City::query()->updateOrCreate(
                    [
                        'governorate_id' => $governorate->id,
                        'code' => $cityPayload['code'],
                    ],
                    [
                        ...$cityPayload,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
