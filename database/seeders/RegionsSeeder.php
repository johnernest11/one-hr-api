<?php

namespace Database\Seeders;

use App\Models\Address\Region;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RegionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rawData = file_get_contents(base_path('database/seeders/dumps/regions.json'));
        $regionsJson = json_decode($rawData, true);

        $regions = [];
        foreach ($regionsJson as $region) {
            $regions[] = [
                'code' => $region['code'],
                'name' => $region['name'],
                'alt_name' => $region['altName'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        Region::insert($regions);
    }
}
