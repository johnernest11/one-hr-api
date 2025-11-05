<?php

namespace Database\Factories\ComprehensiveRecords;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndividualGovernmentIdFactory>
 */
class IndividualGovernmentIdFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gov_issued_id' => fake()->name(),
            'gov_id_no' => fake()->bothify('##-#######'),
            'gov_issuance' => fake()->address(),
        ];
    }
}
