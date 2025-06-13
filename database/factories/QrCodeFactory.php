<?php

namespace Database\Factories;

use App\Models\ComprehensiveRecords\Employee;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use Crypt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QrCodeFactory>
 */
class QrCodeFactory extends Factory
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
            'qr_code_value' => Crypt::encrypt($employee->id_number),
            'last_generated_at' => fake()->dateTime(),
            'is_active' => fake()->boolean(100),
        ];
    }
}
