<?php

namespace Database\Factories\ComprehensiveRecords;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndividualMembershipFactory>
 */
class IndividualMembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'association_organization' => fake()->company(),
        ];
    }
}
