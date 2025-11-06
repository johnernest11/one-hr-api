<?php

namespace Database\Factories\Libraries;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Libraries\Division>
 */
class DivisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->jobTitle(),
            'head_user_id' => 1,
            'added_by_user_id' => 1,
            'last_modified_by_user_id' => 1,
        ];
    }
}
