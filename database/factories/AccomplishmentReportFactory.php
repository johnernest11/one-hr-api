<?php

namespace Database\Factories;

use App\Enums\ARStatus;
use App\Models\AccomplishmentReport;
use App\Models\ARRows;
use App\Models\UserProfile;
use Carbon\Carbon;
use ConversionHelper;
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
            //'status'=>fake()->randomElement(ConversionHelper::enumToArray(ARStatus::class)),
            //'user_profile_id' => UserProfile::factory(),
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
    public function hasProfile(): Factory
    {
        return $this->state(function () {
            return ['user_profile_id' => UserProfile::factory()];
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
