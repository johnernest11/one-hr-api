<?php

namespace Database\Factories\ComprehensiveRecords;

use App\Models\Item;
use App\Models\Libraries\Division;
use App\Models\Libraries\Office;
use App\Models\Libraries\Program;
use App\Models\Libraries\SalaryGrade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeFactory>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $division = Division::first();
        $section = $division->sectionOrUnits()->first();

        return [
            'id_number' => (string) fake()->randomNumber(9),
            'item_id' => Item::factory(),
            'salary_grade_id' => SalaryGrade::first()->id,
            'program_id' => Program::first()->id,
            'office_id' => Office::first()->id,
            'division_id' => $division->id,
            'section_or_unit_id' => $section->id,
            'agency_employee_no' => (string) fake()->randomNumber(9),
        ];
    }
}
