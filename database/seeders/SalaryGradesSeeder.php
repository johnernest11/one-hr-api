<?php

namespace Database\Seeders;

use App\Models\Libraries\SalaryGrade;
use Carbon\Carbon;

class SalaryGradesSeeder extends CiCdCompliantSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rawData = file_get_contents(base_path('database/seeders/dumps/salary_grades_04292025.json'));
        $sgJson = json_decode($rawData, true);

        $salary_grades = [];
        foreach ($sgJson as $salary_grade) {
            $salary_grades[] = [
                'id' => $salary_grade['id'],
                'nbc_no' => $salary_grade['nbc_no'],
                'effective_date' => $salary_grade['effective_date'],
                'tranche' => $salary_grade['tranche'],
                'salary_grade' => $salary_grade['salary_grade'],
                'step' => $salary_grade['step'],
                'amount' => $salary_grade['amount'],
                'active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        SalaryGrade::insert($salary_grades);
    }

    protected function tableName(): string
    {
        return app(SalaryGrade::class)->getTable();
    }

    /** {@inheritDoc} */
    public function shouldRun(): bool
    {
        return $this->tableIsEmpty();
    }
}
