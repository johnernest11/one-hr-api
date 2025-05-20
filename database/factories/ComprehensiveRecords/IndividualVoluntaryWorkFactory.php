<?php

namespace Database\Factories\ComprehensiveRecords;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndividualVoluntaryWorkFactory>
 */
class IndividualVoluntaryWorkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'is_current_org' => fake()->boolean(),
            'org_name' => fake()->company(),
            'org_address' => fake()->address(),
            'from' => fake()->date(),
            'to' => fake()->date('Y-m-d', '+5 years'), // Example: 5 years after 'from' date
            'number_of_hours' => fake()->numberBetween(40, 48), // Example: Between 40 and 48 hours per week
            'position_nature_of_work' => fake()->jobTitle(),

        ];
    }
}
