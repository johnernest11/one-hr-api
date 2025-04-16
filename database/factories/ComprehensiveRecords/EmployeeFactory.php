<?php

namespace Database\Factories\ComprehensiveRecords;

use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeFactory>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'individual_basic_detail_id' => IndividualBasicDetail::factory(),
            'id_number' => (string) fake()->randomNumber(9),
            'item_id' => Item::factory(),
            'agency_employee_no' => (string) fake()->randomNumber(9),
        ];
    }
}
