<?php

namespace Database\Factories\ComprehensiveRecords;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndividualRecognitionFactory>
 */
class IndividualRecognitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recognition' => fake()->sentence(),
        ];
    }
}
