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
            'number' => fake()->unique()->randomNumber(),
            'date_of_creation' => fake()->date(),
            'status' => 'Unfilled',
            'date_filled_up' => fake()->date(),
            'employment_status' => fake()->randomElement(ConversionHelper::enumToArray(EmploymentStatus::class)),
            'position_id' => 1,
        ];
    }
}
