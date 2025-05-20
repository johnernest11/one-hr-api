<?php

namespace Database\Factories\ComprehensiveRecords;

use App\Enums\BloodType;
use App\Enums\Citizenship;
use App\Enums\CitizenshipAcquisition;
use App\Enums\CivilStatus;
use App\Enums\ExtensionNameCategory;
use App\Models\ComprehensiveRecords\Employee;
use App\Models\ComprehensiveRecords\IndividualAddress;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\ComprehensiveRecords\IndividualContactInfo;
use App\Models\ComprehensiveRecords\IndividualEducationalBackground;
use App\Models\ComprehensiveRecords\IndividualEligibility;
use App\Models\ComprehensiveRecords\IndividualFamily;
use App\Models\ComprehensiveRecords\IndividualLnd;
use App\Models\ComprehensiveRecords\IndividualMembership;
use App\Models\ComprehensiveRecords\IndividualRecognition;
use App\Models\ComprehensiveRecords\IndividualSkillsHobby;
use App\Models\ComprehensiveRecords\IndividualVoluntaryWork;
use App\Models\ComprehensiveRecords\IndividualWorkExperience;
use ConversionHelper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndividualBasicDetailFactory>
 */
class IndividualBasicDetailFactory extends Factory
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
            'birthday' => fake()->date(),
            'sex' => fake()->randomElement(['male', 'female']),

            'place_of_birth' => fake()->word(),
            'civil_status' => fake()->randomElement(ConversionHelper::enumToArray(CivilStatus::class)),
            'height' => fake()->randomFloat(2), // Updated to prevent floating-point precision issue
            'weight' => fake()->randomNumber(2),
            'blood_type' => fake()->randomElement(ConversionHelper::enumToArray(BloodType::class)),
            'gsis_no' => (string) fake()->randomNumber(9),
            'pag_ibig_no' => (string) fake()->randomNumber(9),
            'philhealth_no' => (string) fake()->randomNumber(9),
            'sss_no' => (string) fake()->randomNumber(9),
            'tin' => (string) fake()->randomNumber(9),
            'citizenship' => fake()->randomElement(ConversionHelper::enumToArray(Citizenship::class)),
            'citizenship_acquisition' => fake()->randomElement(ConversionHelper::enumToArray(CitizenshipAcquisition::class)),
        ];
    }

    public function configure()
    {
        // Upon creating IndividualBasicDetail, the following relationships should also be created.
        // @todo: Update this as we add new related models.
        return $this->afterCreating(function (IndividualBasicDetail $individualBasicDetail) {
            Employee::factory()->for($individualBasicDetail)->create();
            IndividualAddress::factory()->for($individualBasicDetail)->create();
            IndividualContactInfo::factory()->for($individualBasicDetail)->create();
            IndividualFamily::factory()->for($individualBasicDetail)->create();
            IndividualEducationalBackground::factory()->for($individualBasicDetail)->create();
            IndividualEligibility::factory()->for($individualBasicDetail)->create();
            IndividualWorkExperience::factory()->for($individualBasicDetail)->create();
            IndividualVoluntaryWork::factory()->for($individualBasicDetail)->create();
            IndividualLnd::factory()->for($individualBasicDetail)->create();
            IndividualSkillsHobby::factory()->for($individualBasicDetail)->create();
            IndividualRecognition::factory()->for($individualBasicDetail)->create();
            IndividualMembership::factory()->for($individualBasicDetail)->create();
        });
    }
}
