<?php

namespace Database\Factories;

use App\Enums\WeekNumber;
use App\Models\AccomplishmentReport;
use Carbon\Carbon;
use ConversionHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AccomplishmentReport>
 */
class ARRowsFactory extends Factory
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

        return [
            //'accomplishment_report_id' => AccomplishmentReport::factory(),
            'week_num' => fake()->randomElement(ConversionHelper::enumToArray(WeekNumber::class)),
            'dates_in_week' => $this->generateDatesInWeek($month, $monthStr, $year),
            'specific_activity' => fake()->paragraph(6),
            'highlights' => fake()->paragraph(3),
        ];
    }

    public function generateDatesInWeek(int $month, string $monthStr, int $year): string
    {
        $startDay = rand(1, Carbon::create($year, $month, 1)->daysInMonth - 6);
        $endDay = $startDay + 6;

        return "$startDay - $endDay $monthStr $year";
    }
}
