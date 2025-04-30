<?php

namespace Database\Seeders;

use App\Models\Libraries\Program;
use Carbon\Carbon;

class ProgramsSeeder extends CiCdCompliantSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rawData = file_get_contents(base_path('database/seeders/dumps/programs.json'));
        $programsJson = json_decode($rawData, true);

        $programs = [];
        foreach ($programsJson as $program) {
            $programs[] = [
                'id' => $program['id'],
                'name' => $program['name'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        Program::insert($programs);
    }

    protected function tableName(): string
    {
        return app(Program::class)->getTable();
    }

    /** {@inheritDoc} */
    public function shouldRun(): bool
    {
        return $this->tableIsEmpty();
    }
}
