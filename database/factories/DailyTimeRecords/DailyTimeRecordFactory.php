<?php

namespace Database\Factories\DailyTimeRecords;

use App\Enums\DocumentStatus;
use App\Models\ComprehensiveRecords\Employee;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use ConversionHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyTimeRecordFactory>
 */
class DailyTimeRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $individual = IndividualBasicDetail::factory()->create();
        $employee = Employee::whereBelongsTo($individual)->firstOrFail();

        return [
            'employee_id' => $employee,
            'date' => fake()->date(),
            'employee_remarks' => fake()->text(),
            'hr_remarks' => fake()->text(),
            'status' => fake()->randomElement(ConversionHelper::enumToArray(DocumentStatus::class)),
        ];
    }

    public function setDate(string $date): Factory
    {
        return $this->state(function () use ($date) {
            return ['date' => $date];
        });
    }

    public function setEmployee(Employee|int $employee): Factory
    {
        return $this->state(function () use ($employee) {
            return ['employee_id' => $employee];
        });
    }
}
