<?php

namespace Database\Seeders;

use App\Models\Address\City;
use App\Models\Address\Province;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rawData = file_get_contents(base_path('database/seeders/dumps/cities_municipalities.json'));
        $citiesJson = json_decode($rawData, true);

        $cities = [];
        foreach ($citiesJson as $city) {
            $cities[] = [
                'code' => $city['code'],
                'name' => $city['name'],
                'full_name' => $city['fullName'],
                'alt_name' => $city['altName'],
                'province_id' => Province::where('code', $city['province'])->pluck('id')->first(),
                'classification' => strtoupper($city['classification']),
                'is_capital' => $city['isCapital'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        City::insert($cities);
    }
}
