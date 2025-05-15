<?php

namespace Database\Factories\ComprehensiveRecords;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndividualEligibilityFactory>
 */
class IndividualEligibilityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'eligibility' => fake()->word(),
            'rating' => fake()->randomFloat(2),
            'date_of_examination_conferment' => fake()->dateTimeThisDecade(),
            'place_of_examination' => fake()->address(),
            'license_number' => fake()->word(),
            'license_date_of_validity' => fake()->dateTimeInInterval('+2 years'),
        ];
    }
}
