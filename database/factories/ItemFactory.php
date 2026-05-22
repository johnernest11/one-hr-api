<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use ConversionHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Core Identity
            'number' => fake()->unique()->randomNumber(5, true),
            'date_of_creation' => fake()->date(),

            // Organization Data (Foreign Keys)
            // hardcoded to 1 for simplicity, or change to: \App\Models\Division::factory()
            'division_id' => 1,
            'section_or_unit_id' => 1,
            'program_id' => 1,
            'office_id' => 1,
            'psipop_id' => 1, // Points to divisions table alias

            // Compensation & Employment Details
            'employment_status' => fake()->randomElement(ConversionHelper::enumToArray(EmploymentStatus::class)),
            'salary_grade_id' => 1,
            'fund_source_id' => 12,

            // Position Details
            'position_id' => 1,
            'item_classification' => fake()->randomElement(['Key Positions', 'Technical', 'Support to Technical', 'Administrative']),

            // Designation and Assignment Details
            'designation' => fake()->word().' Officer',
            'date_of_designation' => fake()->date(),
            'special_order_number' => 'SO-'.fake()->year().'-'.fake()->randomNumber(4),

            // Position Status & Tracking
            'status' => 'Unfilled',
            'mode_of_accession' => null,
            'date_filled_up' => null,
            'history_of_position' => fake()->paragraph(),
            'former_incumbent' => fake()->name(),
            'mode_of_separation' => null,
            'date_of_vacant' => fake()->date(),
            'remarks_of_vacancy' => fake()->sentence(),
            'status_of_vacant_position' => 'For Advertisement',
            'remarks' => fake()->sentence(),
        ];
    }
}
