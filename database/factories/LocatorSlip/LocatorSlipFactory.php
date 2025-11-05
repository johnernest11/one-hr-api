<?php

namespace Database\Factories\LocatorSlip;

use App\Enums\DocumentStatus;
use App\Enums\LocatorFormType;
use App\Enums\Period;
use App\Models\ComprehensiveRecords\Employee;
use ConversionHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LocatorSlip\LocatorSlip>
 */
class LocatorSlipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        return [
            'employee_id' => Employee::factory(),
            'locator_slip_no' => fake()->text(),
            'date' => fake()->date(),
            'period' => fake()->randomElement(ConversionHelper::enumToArray(Period::class)),
            'status' => fake()->randomElement(ConversionHelper::enumToArray(DocumentStatus::class)),
            'form_type' => fake()->randomElement(ConversionHelper::enumToArray(LocatorFormType::class)),
        ];
    }

    public function setEmployee(Employee|int $employee): Factory
    {
        return $this->state(function () use ($employee) {
            return ['employee_id' => $employee];
        });
    }
}
