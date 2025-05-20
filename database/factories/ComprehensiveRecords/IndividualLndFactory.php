<?php

namespace Database\Factories\ComprehensiveRecords;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndividualLndFactory>
 */
class IndividualLndFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'from' => fake()->date(),
            'to' => fake()->date(),
            'number_of_hours' => fake()->numberBetween(1, 40), // Example: Between 1 and 40 hours
            'type' => fake()->word(),
            'conducted_sponsor' => fake()->company(),
        ];
    }
}
