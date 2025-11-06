<?php

namespace Database\Factories\DailyTimeRecords;

use App\Models\DailyTimeRecords\DailyTimeRecord;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * @extends Factory<TimeLogFactory>
 */
class TimeLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'daily_time_record_id' => DailyTimeRecord::factory(),
            'date' => Carbon::today()->toDateString(),
            'scanned_time' => fake()->time('H:i'),
            'is_in' => true,
        ];
    }

    /**
     * Define sequence for alternate is_in values.
     * This is to simulate the logic of the time logs wherein it will alternately set if it is time in/out.
     *
     * @return TimeLogFactory
     */
    public function alternatingIsIn(): Factory
    {
        return $this->state(new Sequence(
            ['is_in' => true],
            ['is_in' => false],
        ));
    }

    public function forDailyTimeRecord(DailyTimeRecord $dailyTimeRecord): Factory
    {
        return $this->state(function (array $attributes) use ($dailyTimeRecord) {
            return [
                'daily_time_record_id' => $dailyTimeRecord->id,
            ];
        });
    }

    public function setDate(string $date): Factory
    {
        return $this->state(function () use ($date) {
            return [
                'daily_time_record_id' => DailyTimeRecord::factory()->state(['date' => $date]),
                'date' => $date,
            ];
        });
    }
}
