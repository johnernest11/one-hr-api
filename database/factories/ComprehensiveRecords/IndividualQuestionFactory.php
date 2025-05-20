<?php

namespace Database\Factories\ComprehensiveRecords;

use App\Models\ComprehensiveRecords\IndividualQuestion;
use App\Models\Libraries\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndividualQuestionFactory>
 */
class IndividualQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            /* ------------------------------- Question 34 ------------------------------ */
            'q34_a' => fake()->boolean(),
            'q34_b' => fake()->boolean(),
            'q34_details' => fake()->word(),
            /* ------------------------------- Question 35 ------------------------------ */
            'q35_a' => fake()->boolean(),
            'q35_a_details' => fake()->word(),
            'q35_b' => fake()->boolean(),
            'q35_b_date_filed' => fake()->date(),
            'q35_b_status' => fake()->word(),
            /* ------------------------------- Question 36 ------------------------------ */
            'q36' => fake()->boolean(),
            'q36_details' => fake()->word(),
            /* ------------------------------- Question 37 ------------------------------ */
            'q37' => fake()->boolean(),
            'q37_details' => fake()->word(),
            /* ------------------------------- Question 38 ------------------------------ */
            'q38_a' => fake()->boolean(),
            'q38_a_details' => fake()->word(),
            'q38_b' => fake()->boolean(),
            'q38_b_details' => fake()->word(),
            /* ------------------------------- Question 39 ------------------------------ */
            'q39' => fake()->boolean(100), // set to 100% true to test the countries
            /* ------------------------------- Question 40 ------------------------------ */
            'q40_a_indigenous_group' => fake()->boolean(),
            'q40_a_details' => fake()->word(),
            'q40_b_pwd' => fake()->boolean(),
            'q40_b_details' => fake()->word(),
            'q40_c_solo_parent' => fake()->boolean(),
            'q40_c_details' => fake()->word(),
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (IndividualQuestion $individualQuestion) {
            $randomCountries = Country::inRandomOrder()->limit(mt_rand(1, 5))->get(); // Get 1 to 5 random countries
            $individualQuestion->countries()->sync($randomCountries);
        });
    }
}
