<?php

namespace Database\Factories\ComprehensiveRecords;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndividualSkillsHobbyFactory>
 */
class IndividualSkillsHobbyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'skill_hobby' => fake()->randomElement([
                'Programming',
                'Playing Guitar',
                'Cooking',
                'Dancing',
                'Reading',
                'Sports',
                'Writing',
                'Painting',
            ]),
        ];
    }
}
