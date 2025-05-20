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
        $user = (new User)->setConnection('one_account')->create([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'Sample_Password_1',
            'active' => true,
            'email_verified_at' => fake()->dateTime(),
        ]);

        return [
            'user_id' => $user->id,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'middle_name' => fake()->lastName(),
            'ext_name' => fake()->randomElement(['Jr.', 'Sr.', 'III', 'IV']),
            'mobile_number' => '+63906464'.fake()->unique()->randomNumber(4),
            'telephone_number' => '+63271828'.fake()->randomNumber(4),
            'sex' => fake()->randomElement(['male', 'female']),
            'birthday' => fake()->date(),
            'profile_picture_path' => 'avatars/'.fake()->uuid().'.jpg',

        ];
    }
}
