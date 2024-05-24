<?php

namespace Database\Factories;

use App\Enums\VerificationMethod;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\VerificationFactor;
use Crypt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VerificationFactor>
 */
class VerificationFactorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->has(UserProfile::factory()),
            'type' => fake()->randomElement([VerificationMethod::EMAIL_CHANNEL, VerificationMethod::GOOGLE_AUTHENTICATOR]),
            'secret' => Crypt::encrypt(fake()->uuid()),
            'enrolled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @State
     * User has their email unverified
     */
    public function unEnrolled(): Factory
    {
        return $this->state(function () {
            return ['enrolled_at' => null];
        });
    }
}
