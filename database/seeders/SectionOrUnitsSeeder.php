<?php

namespace Database\Seeders;

use App\Models\Libraries\SectionOrUnit;
use Carbon\Carbon;

class SectionOrUnitsSeeder extends CiCdCompliantSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rawData = file_get_contents(base_path('database/seeders/dumps/sections_or_units.json'));
        $sectionOrUnitJson = json_decode($rawData, true);

        $sectionsUnits = [];
        foreach ($sectionOrUnitJson as $sectionUnit) {
            $sectionsUnits[] = [
                'id' => $sectionUnit['id'],
                'name' => $sectionUnit['name'],
                'division_id' => $sectionUnit['division_id'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        SectionOrUnit::insert($sectionsUnits);
    }

    protected function tableName(): string
    {
        return app(SectionOrUnit::class)->getTable();
    }

    /** {@inheritDoc} */
    public function shouldRun(): bool
    {
        return $this->tableIsEmpty();
    }
}
