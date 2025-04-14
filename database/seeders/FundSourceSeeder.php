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
        $fundsJson = json_decode($rawData, true);

        $funds = [];
        foreach ($fundsJson as $fund) {
            $funds[] = [
                'id' => $fund['id'],
                'name' => $fund['name'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        FundSource::insert($funds);
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
