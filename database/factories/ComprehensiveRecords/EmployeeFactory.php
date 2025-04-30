<?php

namespace Database\Factories\ComprehensiveRecords;

use App\Models\Item;
use App\Models\Libraries\SalaryGrade;
use App\Models\Libraries\SectionOrUnit;
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
        return [
            'id_number' => (string) fake()->randomNumber(9),
            'item_id' => Item::factory(),
            'salary_grade_id' => SalaryGrade::first()->id,
            'section_or_unit_id' => SectionOrUnit::first()->id,
            'agency_employee_no' => (string) fake()->randomNumber(9),
        ];
    }
}
