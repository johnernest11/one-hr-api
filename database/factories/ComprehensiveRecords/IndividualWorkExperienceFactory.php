<?php

namespace Database\Factories\ComprehensiveRecords;

use App\Enums\EmploymentStatus;
use App\Models\Libraries\SalaryGrade;
use ConversionHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndividualWorkExperienceFactory>
 */
class IndividualWorkExperienceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'is_current_work' => fake()->boolean(100), // Always true
            'inclusive_date_from' => fake()->date(),
            'inclusive_date_to' => fake()->date(),
            'position_title' => fake()->jobTitle(),
            'department_agency_office_company' => fake()->company(),
            'monthly_salary' => fake()->numerify('#####'),
            'salary_grade_id' => SalaryGrade::first()->id,
            'status_of_appointment' => fake()->randomElement(ConversionHelper::enumToArray(EmploymentStatus::class)),
            'is_gov_service' => fake()->boolean(),
        ];
    }
}
