<?php

namespace Database\Seeders;

use App\Models\Libraries\Division;
use Carbon\Carbon;

class DivisionsSeeder extends CiCdCompliantSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rawData = file_get_contents(base_path('database/seeders/dumps/divisions.json'));
        $divisionsJson = json_decode($rawData, true);

        $divisions = [];
        foreach ($divisionsJson as $division) {
            $divisions[] = [
                'id' => $division['id'],
                'name' => $division['name'],
                'office_id' => $division['office_location_id'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        Division::insert($divisions);
    }

    protected function tableName(): string
    {
        return app(Division::class)->getTable();
    }

    /** {@inheritDoc} */
    public function shouldRun(): bool
    {
        return $this->tableIsEmpty();
    }
}
