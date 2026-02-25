<?php

namespace Database\Factories\DailyTimeRecords;

use App\Models\DailyTimeRecords\DailyTimeRecord;
use App\Models\Libraries\Office;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
        Storage::fake('s3');
        $file = UploadedFile::fake()->image('fake_image.jpg', 500, 500);

        return [
            'daily_time_record_id' => DailyTimeRecord::factory(),
            'date' => Carbon::today()->toDateString(),
            'scanned_time' => fake()->time('H:i'),
            'is_in' => true,
            'office_id' => Office::first()->id,
            'captured_image_path' => $file->store('images/timelogs', 's3'),
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
