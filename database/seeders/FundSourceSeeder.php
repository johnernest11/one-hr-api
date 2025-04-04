<?php

namespace Database\Seeders;

use App\Models\Libraries\FundSource;
use Carbon\Carbon;

class FundSourceSeeder extends CiCdCompliantSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rawData = file_get_contents(base_path('database/seeders/dumps/fund-sources.json'));
        $positionsJson = json_decode($rawData, true);

        $positions = [];
        foreach ($positionsJson as $position) {
            $positions[] = [
                'id' => $position['id'],
                'name' => $position['name'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        FundSource::insert($positions);
    }

    protected function tableName(): string
    {
        return app(FundSource::class)->getTable();
    }

    /** {@inheritDoc} */
    public function shouldRun(): bool
    {
        return $this->tableIsEmpty();
    }
}
