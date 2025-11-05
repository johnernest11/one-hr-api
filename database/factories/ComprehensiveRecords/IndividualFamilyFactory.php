<?php

namespace Database\Factories\ComprehensiveRecords;

use App\Enums\ExtensionNameCategory;
use App\Enums\FamilyMemberCategory;
use ConversionHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndividualFamilyFactory>
 */
class IndividualFamilyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'middle_name' => fake()->name(),
            'ext_name' => fake()->randomElement(ConversionHelper::enumToArray(ExtensionNameCategory::class)),
            'occupation' => fake()->jobTitle(),
            'employers_business_name' => fake()->name(),
            'business_address' => fake()->address(),
            'telephone_no' => fake()->numerify('+6391234567##'), // Randomizing last two digits since it is causing issues otherwise.
            'date_of_birth' => fake()->date(),
            'class' => fake()->randomElement(ConversionHelper::enumToArray(FamilyMemberCategory::class)),
        ];
    }
}
