<?php

namespace Database\Factories;

use App\Models\AccomplishmentReport;
use App\Models\ARRows;
use App\Models\User;
use App\Models\UserProfile;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AccomplishmentReport>
 */
class AccomplishmentReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Generate realistic dates

        $year = fake()->numberBetween(2000, 2030);
        $month = fake()->numberBetween(1, 12);
        $monthStr = Carbon::createFromFormat('m', $month)->format('F');
        $lastDay = Carbon::createFromDate($year, $month, 1)->endOfMonth()->day;
        $randomHalf = fake()->randomElement([1, 16]);
        $periodDates = $randomHalf == 1 ? '1-15' : '16-'.$lastDay;

        return [
            'period' => "$periodDates $monthStr $year",
            'supervisor_notes' => fake()->sentence(),
        ];
    }

    /**
     * @State
     */
    public function isDraft(): Factory
    {
        return $this->state(function () {
            return ['status' => 'draft'];
        });
    }

    /**
     * @State
     */
    public function isDone(): Factory
    {
        return $this->state(function () {
            return ['status' => 'done'];
        });
    }

    /**
     * @State
     */
    public function hasProfile(?User $user = null): Factory
    {
        return $this->state(function () use ($user) {
            if ($user) {
                $userProfile = UserProfile::firstOrCreate(['user_id' => $user->id]);
            } else {
                $user = User::factory()->create([
                    'email' => fake()->unique()->safeEmail(),
                    'username' => fake()->unique()->userName(),
                ]);

                $userProfile = UserProfile::factory()->create([
                    'user_id' => $user->id,
                ]);
            }

            return ['user_profile_id' => $userProfile->id];
        });
    }

    /**
     * Automatically create 3 rows for the accomplishment report
     *
     * @return AccomplishmentReportFactory
     */
    public function configure()
    {
        return $this->afterCreating(function (AccomplishmentReport $accomplishmentReport) {
            ARRows::factory(3)->for($accomplishmentReport)->create();
        });
    }
}
