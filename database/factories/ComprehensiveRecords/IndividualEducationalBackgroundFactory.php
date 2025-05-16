<?php

namespace Database\Factories\ComprehensiveRecords;

use App\Enums\AcademicLevel;
use ConversionHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndividualEducationalBackgroundFactory>
 */
class IndividualEducationalBackgroundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'schools_name' => fake()->company(),
            'education_description' => fake()->jobTitle(),
            'level' => fake()->randomElement(ConversionHelper::enumToArray(AcademicLevel::class)),
            'period_of_attendance_from' => fake()->year(),
            'period_of_attendance_to' => fake()->year(),
            'highest_level_units_earned' => fake()->word(),
            'year_graduated' => fake()->year(),
            'scholarship_academic_honors_received' => fake()->sentence(),
        ];
    }
}
