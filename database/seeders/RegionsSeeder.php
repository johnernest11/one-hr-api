<?php

namespace Database\Seeders;

use App\Models\Address\Region;
use Carbon\Carbon;

class RegionsSeeder extends CiCdCompliantSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rawData = file_get_contents(base_path('database/seeders/dumps/psgc_regions_1q23.json'));
        $regionsJson = json_decode($rawData, true);

        $regions = [];
        foreach ($regionsJson as $region) {
            $regions[] = [
                'id' => $region['reg_id'],
                'code' => $region['code'],
                'name' => $region['name'],
                'code_correspondence' => $region['code_correspondence'],
                'alt_name' => $region['altName'],
                'geo_level' => $region['geo_level'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        Region::insert($regions);
    }

    protected function tableName(): string
    {
        return app(Region::class)->getTable();
    }

    /** {@inheritDoc} */
    public function shouldRun(): bool
    {
        return $this->tableIsEmpty();
    }
}
