<?php

namespace Database\Seeders;

use App\Models\Address\Province;
use App\Models\Address\Region;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ProvincesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rawData = file_get_contents(base_path('database/seeders/dumps/provinces.json'));
        $provincesJson = json_decode($rawData, true);

        $provinces = [];
        foreach ($provincesJson as $province) {
            $provinces[] = [
                'code' => $province['code'],
                'name' => $province['name'],
                'alt_name' => $province['altName'],
                'region_id' => Region::where('code', $province['region'])->pluck('id')->first(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        Province::insert($provinces);
    }
}
