<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserProfile>
 */
class UserProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'middle_name' => fake()->lastName(),
            'ext_name' => fake()->randomElement(['Jr.', 'Sr.', 'III', 'IV']),
            'mobile_number' => '+63906'.fake()->unique()->randomNumber(7),
            'telephone_number' => '+6327'.fake()->randomNumber(7),
            'sex' => fake()->randomElement(['male', 'female']),
            'birthday' => fake()->date(),
            'profile_picture_path' => fake()->filePath(),
        ];
    }
}
