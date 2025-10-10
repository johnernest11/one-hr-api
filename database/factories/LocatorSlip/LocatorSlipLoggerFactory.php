<?php

namespace Database\Factories\LocatorSlip;

use App\Enums\ApprovalType;
use App\Models\LocatorSlip\LocatorSlip;
use ConversionHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LocatorSlip\LocatorSlipLogger>
 */
class LocatorSlipLoggerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'locator_slip_id' => LocatorSlip::factory(),
            'date' => fake()->date(),
            'time_in' => fake()->time('H:i'),
            'time_out' => fake()->time('H:i'),
            'destination' => fake()->text(),
            'purpose' => fake()->text(),
            'approved_for' => fake()->randomElement(ConversionHelper::enumToArray(ApprovalType::class)),
            'duration' => fake()->randomNumber(),
            'remarks' => fake()->text(),
        ];
    }
}
