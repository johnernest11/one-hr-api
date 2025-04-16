<?php

namespace Database\Factories\ComprehensiveRecords;

use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndividualContactInfoFactory>
 */
class IndividualContactInfoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'individual_basic_detail_id' => IndividualBasicDetail::factory(),
            'tel_no' => fake()->numerify('+637255512##'), //Randomizing last two digits since it is causing issues otherwise.
            'mobile_no' => fake()->numerify('+6391234567##'), //Randomizing last two digits since it is causing issues otherwise.
            'email_address' => fake()->unique()->safeEmail(),
        ];
    }
}
