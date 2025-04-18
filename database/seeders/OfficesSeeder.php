<?php

namespace Database\Seeders;

use App\Models\Libraries\Office;
use Carbon\Carbon;

class OfficesSeeder extends CiCdCompliantSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rawData = file_get_contents(base_path('database/seeders/dumps/offices.json'));
        $officesJson = json_decode($rawData, true);

        $offices = [];
        foreach ($officesJson as $office) {
            $offices[] = [
                'id' => $office['id'],
                'name' => $office['name'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        Office::insert($offices);
    }

    protected function tableName(): string
    {
        return app(Office::class)->getTable();
    }

    /** {@inheritDoc} */
    public function shouldRun(): bool
    {
        return $this->tableIsEmpty();
    }
}
