<?php

namespace Database\Factories\ComprehensiveRecords;

use App\Models\Address\Barangay;
use App\Models\Address\City;
use App\Models\Address\Province;
use App\Models\Address\Region;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndividualAddressFactory>
 */
class IndividualAddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $regionId = Region::first()->id;
        $provinceId = Province::where('region_id', $regionId)->inRandomOrder()->first()->id;
        $cityId = City::where('province_id', $provinceId)->inRandomOrder()->first()->id;
        $barangayId = Barangay::first()->id; // don't run full search since there's too many

        return [
            'individual_basic_detail_id' => IndividualBasicDetail::factory(),
            'residential_house_block_lot_no' => fake()->streetAddress(),
            'residential_street' => fake()->streetAddress(),
            'residential_subdivision_village' => fake()->streetAddress(),
            'residential_brgy_id' => $barangayId,
            'residential_citymun_id' => $cityId,
            'residential_province_id' => $provinceId,
            'residential_region_id' => $regionId,
            'residential_zip_code' => fake()->randomNumber(4),
            'permanent_house_block_lot_no' => fake()->streetAddress(),
            'permanent_street' => fake()->streetAddress(),
            'permanent_subdivision_village' => fake()->streetAddress(),
            'permanent_brgy_id' => $barangayId,
            'permanent_citymun_id' => $cityId,
            'permanent_province_id' => $provinceId,
            'permanent_region_id' => $regionId,
            'permanent_zip_code' => fake()->randomNumber(4),
        ];
    }
}
