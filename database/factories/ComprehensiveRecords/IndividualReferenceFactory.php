<?php

namespace Database\Factories\ComprehensiveRecords;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndividualReferenceFactory>
 */
class IndividualReferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'address' => fake()->address(),
            'tel_no' => fake()->numerify('+6391234567##'), // Randomizing last two digits since it is causing issues otherwise.
        ];
    }
}
