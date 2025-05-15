<?php

namespace Tests\Feature;

use App\Enums\PDSFormType;
use App\Enums\Role as RoleEnum;
use App\Models\ComprehensiveRecords\Employee;
use App\Models\ComprehensiveRecords\IndividualAddress;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\ComprehensiveRecords\IndividualContactInfo;
use App\Models\ComprehensiveRecords\IndividualEducationalBackground;
use App\Models\ComprehensiveRecords\IndividualEligibility;
use App\Models\ComprehensiveRecords\IndividualFamily;
use App\Models\ComprehensiveRecords\IndividualWorkExperience;
use App\Models\Item;
use App\Models\User;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use DB;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Str;
use Tests\TestCase;

class IndividualBasicDetailFeatureTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUri = self::BASE_API_URI.'/individual-basic-details';

    private User $user;

    private string $authToken;

    private PersistentAuthTokenManager $tokenManager;

    // @todo update when there's new records
    private array $comprehensive_records_rel = [
        'employee',
        'individualAddress',
        'individualContactInfo',
        'individualFamily',
        'individualEducationalBackground',
        'individualWorkExperience',
    ];

    public function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');

        $user = $this->produceUsers();
        $roles = [RoleEnum::ADMIN, RoleEnum::HR_PPMS_ADMIN];
        $user->syncRoles(fake()->randomElement($roles));
        $this->user = $user; // save random user

        $this->tokenManager = resolve(PersistentAuthTokenManager::class);
        $authTokenExpiration = now()->addMinutes(config('sanctum.expiration'));
        $this->authToken = $this->tokenManager->generateToken($user, $authTokenExpiration, 'mock_token');

    }

    /**
     * This function is for truncating all tables in the test database.
     *
     * The problem that this aims to solve:
     * During the test for fulltext search, we have to commit the changes to the database for the fulltext to work properly.
     * After all of the assertions, we have to cleanup the database.
     *
     * We need to truncate every record that was commited to ensure that the succeeding tests will proceed as expected.
     */
    public function truncate_test_db(): void
    {
        DB::statement('SET foreign_key_checks=0'); // Temporarily remove foreign key constraint to truncate tables without running into errors.
        $databaseName = DB::getDatabaseName();
        $tables = DB::select("SELECT * FROM information_schema.tables WHERE table_schema = '$databaseName'");
        foreach ($tables as $table) {
            $name = $table->TABLE_NAME;
            DB::table($name)->truncate();
        }
        DB::statement('SET foreign_key_checks=1'); // Reenable foreign key constraint.
    }

    public function test_it_can_search_for_individual_data(): void
    {
        $individual = IndividualBasicDetail::factory()->create(); // Create a test record

        // Problem: Fulltext does not work in tests: https://dev.mysql.com/doc/refman/en/innodb-fulltext-index.html#innodb-fulltext-index-transaction
        // To resolve this, we need to commit the transaction first and clean the database later.
        // Reference for the solution:
        // https://laracasts.com/discuss/channels/testing/issue-with-data-persistenceeloquent-query-when-running-tests?page=1&replyId=926176
        DB::commit(); // Commit the changes so that the fulltext search will work.

        $q = $individual->first_name;
        $response = $this->withToken($this->authToken)->getJson("$this->baseUri/search?query=$q");
        $response->assertStatus(200);

        $responseData = $response->decodeResponseJson()['data'];
        $this->assertNotEmpty($responseData, 'Search returned no results.');

        $firstSearchResult = $responseData[0];
        $this->assertStringContainsString($q, $firstSearchResult['first_name']);

        // Assertions done, cleanup the database.
        $this->truncate_test_db();

        // Check if the database has been successfully truncated. Testing for one table only.
        $initialCount = DB::table('employees')->count();
        $this->assertEquals(0, $initialCount, 'Database table should be empty after cleanup.');

    }

    public static function validCreateIndividualBasicDetailsInputs(): array
    {
        $requiredFieldsOnly = [
            'individual' => [
                'first_name' => 'ppms',
                'last_name' => 'ppms',
                'birthday' => '2000-01-01',
                'sex' => 'male',

                'place_of_birth' => 'San Fernando',
                'civil_status' => 'Single',
                'height' => '2.0',
                'weight' => '70',
                'blood_type' => 'AB-',
                'pag_ibig_no' => '09234886',
                'philhealth_no' => '09234886',
                'sss_no' => '09234886',
                'tin' => '09234886',
                'citizenship' => 'Filipino',
                'citizenship_acquisition' => 'By Birth',
            ],

            'employee' => [
                'salary_grade_id' => 1,
                'office_id' => 1,
                'division_id' => 8,
                'section_or_unit_id' => 46,
            ],

            'individual_address' => [
                [
                    'residential_house_block_lot_no' => '#001 House',
                    'residential_street' => 'Sunshine Street',
                    'residential_brgy_id' => 1,
                    'residential_citymun_id' => 3,
                    'residential_province_id' => 3,
                    'residential_region_id' => 2,
                    'residential_zip_code' => '3000',
                    'permanent_brgy_id' => 1,
                    'permanent_citymun_id' => 3,
                    'permanent_province_id' => 3,
                    'permanent_region_id' => 2,
                    'permanent_zip_code' => '3000',

                ],
            ],

            'individual_contact_info' => [
                [
                    'mobile_no' => '+639123456789',
                    'email_address' => 'ppms.admin@test.com',
                ],
            ],
            'individual_family' => [
                [
                    'first_name' => 'Father',
                    'last_name' => 'Father',
                    'class' => 'Father',
                ],
            ],
            'individual_educational_background' => [
                [
                    'level' => 'Elementary',
                ],
            ],

        ];

        $allFields = [
            'individual' => [
                'first_name' => 'ppms',
                'last_name' => 'ppms',
                'middle_name' => 'test',
                'ext_name' => 'I',
                'birthday' => '2000-01-01',
                'sex' => 'male',

                'place_of_birth' => 'San Fernando',
                'civil_status' => 'Single',
                'height' => '2.0',
                'weight' => '70',
                'blood_type' => 'AB-',
                'gsis_no' => '09234886',
                'pag_ibig_no' => '09234886',
                'philhealth_no' => '09234886',
                'sss_no' => '09234886',
                'tin' => '09234886',
                'citizenship' => 'Filipino',
                'citizenship_acquisition' => 'By Birth',
            ],

            'employee' => [
                'id_number' => '01111',
                'salary_grade_id' => 1,
                'program_id' => 1,
                'office_id' => 1,
                'division_id' => 8,
                'section_or_unit_id' => 46,
                'agency_employee_no' => '01111',
            ],

            'individual_address' => [
                [
                    'residential_house_block_lot_no' => '#001 House',
                    'residential_street' => 'Sunshine Street',
                    'residential_subdivision_village' => 'One Subdivision',
                    'residential_brgy_id' => 1,
                    'residential_citymun_id' => 3,
                    'residential_province_id' => 3,
                    'residential_region_id' => 2,
                    'residential_zip_code' => '3000',
                    'permanent_house_block_lot_no' => '#001 House',
                    'permanent_street' => 'Sunshine Street',
                    'permanent_subdivision_village' => 'One Subdivision',
                    'permanent_brgy_id' => 1,
                    'permanent_citymun_id' => 3,
                    'permanent_province_id' => 3,
                    'permanent_region_id' => 2,
                    'permanent_zip_code' => '3000',

                ],
            ],

            'individual_contact_info' => [
                [
                    'tel_no' => '+63725551212',
                    'mobile_no' => '+639123456789',
                    'email_address' => 'ppms.admin@test.com',
                ],
            ],
            'individual_family' => [
                [
                    'first_name' => 'Father',
                    'last_name' => 'Father',
                    'middle_name' => 'Father',
                    'ext_name' => 'I',
                    'occupation' => 'Gardener',
                    'employers_business_name' => 'Test Business',
                    'business_address' => 'Test Address',
                    'telephone_no' => '+639123456789',
                    'class' => 'Father',
                    'date_of_birth' => '1979-01-01',
                ],
            ],
            'individual_educational_background' => [
                [
                    'schools_name' => 'School Test 1',
                    'education_description' => 'Elementary',
                    'level' => 'Elementary',
                    'period_of_attendance_from' => '2000',
                    'period_of_attendance_to' => '2010',
                    'highest_level_units_earned' => 'Graduate',
                    'year_graduated' => '2010',
                    'scholarship_academic_honors_received' => null,
                ],
            ],
            // -----> C2 starts here <-----
            'individual_eligibility' => [
                [
                    'eligibility' => 'Career Service Professional Examination',
                    'rating' => 91.4,
                    'date_of_examination_conferment' => '2024-08-11',
                    'place_of_examination' => 'San Fernando City, La Union',
                    'license_number' => null,
                    'license_date_of_validity' => null,
                ],
            ],
            'individual_work_experience' => [
                [
                    'is_current_work' => true,
                    'inclusive_date_from' => '2025-01-01',
                    'inclusive_date_to' => null,
                    'position_title' => 'Web Developer II',
                    'department_agency_office_company' => 'Test Company',
                    'monthly_salary' => 30000,
                    'salary_grade_id' => null,
                    'status_of_appointment' => 'Permanent',
                    'is_gov_service' => false,
                ],
            ],

        ];

        $missingRequiredFields = Arr::except(
            $allFields,
            ['individual']
        );

        return [
            [$requiredFieldsOnly, 201],
            [$allFields, 201],
            [$missingRequiredFields, 422],
        ];
    }

    /**
     * @dataProvider validCreateIndividualBasicDetailsInputs
     *
     * @note we can't use Eloquent nor faker in data providers
     */
    public function test_it_can_create_individual_basic_details($input, $statusCode): void
    {
        if ($statusCode == 201) {
            // Generate Item and update array
            $generatedItem = Item::factory()->create();

            $input['employee']['item_id'] = $generatedItem->id;
        }

        $response = $this->withToken($this->authToken)->postJson($this->baseUri, $input);
        $response->assertStatus($statusCode);

        if ($statusCode !== 422) {
            $response = $response->decodeResponseJson()['data'];
            $createdIndividual = IndividualBasicDetail::find($response['id']);

            // check if record exists
            $this->assertNotEmpty($createdIndividual);

            // check if related records are created
            foreach (array_keys($input) as $relationshipName) {
                $relationshipName = Str::camel($relationshipName);
                if ($relationshipName == 'individual') {
                    continue;
                }
                $related = $createdIndividual->{$relationshipName};

                if ($related instanceof Model) {
                    $this->assertNotNull($related, "Relationship '{$relationshipName}' on model '{$createdIndividual->getTable()}' is null.");
                    $this->assertNotEquals(0, $related->getKey(), "Relationship '{$relationshipName}' on model '{$createdIndividual->getTable()}' has no data (ID is 0).");
                } elseif ($related instanceof Collection) {
                    $this->assertNotEmpty($related, "Relationship '{$relationshipName}' on model '{$createdIndividual->getTable()}' is empty.");
                } else {
                    $this->fail("Relationship '{$relationshipName}' on model '{$createdIndividual->getTable()}' is not a loaded Eloquent model or collection.");
                }
            }
        }
    }

    public function test_it_can_read_all_individuals_data(): void
    {
        $individuals = IndividualBasicDetail::factory(5)->create();

        $response = $this->withToken($this->authToken)->getJson($this->baseUri);
        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
    }

    public function test_it_can_read_individual_data_by_id(): void
    {
        $individual = IndividualBasicDetail::factory()->create();
        $find_individual_data = IndividualBasicDetail::find($individual->id);

        $response = $this->withToken($this->authToken)->getJson("$this->baseUri/$find_individual_data->id");
        $response->assertStatus(200);
    }

    /**
     * Asserts that two arrays are equal only for the keys that exist in both.
     *
     * Use case: Comparing response and request payload, wherein the response will contain fields that are not in the request like created_at, updated_at, etc.
     *
     * @param  array  $expected  The expected array.
     * @param  array  $actual  The actual array.
     */
    protected function assertArrayEqualsIntersecting(array $expected, array $actual): void
    {
        $intersectingKeys = array_intersect_key($expected, $actual);
        $filteredExpected = array_intersect_key($expected, $intersectingKeys);
        $filteredActual = array_intersect_key($actual, $intersectingKeys);

        $this->assertEquals($filteredExpected, $filteredActual);
    }

    /**
     * Generate test data.
     *
     * @param  $form_type  can be C1, C2, C3, C4 and null. Pass nothing to generate test data to all models.
     */
    public function generate_test_data($form_type = null): array
    {
        // Generate random data
        // @todo: Update as we add new models.
        $testIndividual = IndividualBasicDetail::factory()->make()->toArray();
        $testEmployee = Employee::factory()->make()->toArray();
        $testAddress = IndividualAddress::factory()->make()->toArray();
        $testContactInfo = IndividualContactInfo::factory()->make()->toArray();
        $testFamily = IndividualFamily::factory()->make()->setAppends([])->toArray(); // remove appended attributes for testing.
        $testEducation = IndividualEducationalBackground::factory()->make()->toArray();
        $testEligibility = IndividualEligibility::factory()->make()->toArray();
        $testWorkExperience = IndividualWorkExperience::factory()->make()->toArray();

        //@todo Update as new models are added until all forms are completed
        // Combine data and structure it so that it is similar to the request body
        $c1_request = [
            'form_type' => PDSFormType::C1->value,
            'individual' => $testIndividual,
            'employee' => $testEmployee,
            'individual_address' => [$testAddress],
            'individual_contact_info' => [$testContactInfo],
            'individual_family' => [$testFamily],
            'individual_educational_background' => [$testEducation],
        ];

        $c2_request = [
            'form_type' => PDSFormType::C2->value,
            'individual_eligibility' => [$testEligibility],
            'individual_work_experience' => [$testWorkExperience],
        ];

        $all_request = array_merge(Arr::except($c1_request, 'form_type'), Arr::except($c2_request, 'form_type'));

        $requestData = match ($form_type) {
            PDSFormType::C1->value => $c1_request,
            PDSFormType::C2->value => $c2_request,
            default => $all_request,
        };

        return $requestData;
    }

    public function test_it_can_update_c1(): void
    {
        $individuals = IndividualBasicDetail::factory(5)->create();
        $firstIndividual = $individuals->first();
        // Get first record in hasMany relationship.
        // @todo: Update as we add new models.
        $firstFamily = $firstIndividual->individualFamily()->first();
        $firstEducation = $firstIndividual->individualEducationalBackground()->first();

        // Generate updated data
        $newInfo = $this->generate_test_data(PDSFormType::C1->value);

        // Add the correct id on request body.
        $newInfo['employee']['id'] = $firstIndividual->employee->id;
        $newInfo['individual_address'][0]['id'] = $firstIndividual->individualAddress->id;
        $newInfo['individual_contact_info'][0]['id'] = $firstIndividual->individualContactInfo->id;
        $newInfo['individual_family'][0]['id'] = $firstFamily->id;
        $newInfo['individual_educational_background'][0]['id'] = $firstEducation->id;

        // Update
        $response = $this->withToken($this->authToken)->putJson("$this->baseUri/$firstIndividual->id", $newInfo);
        $response->assertStatus(200);

        // Check if the updated data matches the response result
        $response = $response->decodeResponseJson()['data'];

        foreach ($response as $key => $value) {
            if (array_key_exists($key, $newInfo['individual'])) { //assertion for individual
                $this->assertEquals($newInfo['individual'][$key], $value);
            }

            if (is_array($value) and array_key_exists($key, $newInfo)) {
                // if array, match with the equivalent key & value pair in updatedData
                if ($key == 'employee') { // employee is not nested like the rest of the arrays hence the separate assertion
                    $this->assertArrayEqualsIntersecting($newInfo[$key], $value);

                    continue;
                }

                $this->assertArrayEqualsIntersecting($newInfo[$key][0], $value);

            }
        }
    }

    public function test_it_can_update_c2(): void
    {
        $individuals = IndividualBasicDetail::factory(5)->create();
        $firstIndividual = $individuals->first();
        // Get first record in hasMany relationship.
        // @todo: Update as we add new models.
        $firstEligibility = $firstIndividual->individualEligibility()->first();
        $firstWorkExperience = $firstIndividual->individualWorkExperience()->first();

        // Generate updated data
        $newInfo = $this->generate_test_data(PDSFormType::C2->value);

        // Add the correct id on request body.
        $newInfo['individual_eligibility'][0]['id'] = $firstEligibility->id;
        $newInfo['individual_work_experience'][0]['id'] = $firstWorkExperience->id;

        // Update
        $response = $this->withToken($this->authToken)->putJson("$this->baseUri/$firstIndividual->id", $newInfo);
        $response->assertStatus(200);

        // Check if the updated data matches the response result
        $response = $response->decodeResponseJson()['data'];

        foreach ($response as $key => $value) {
            if (is_array($value) and array_key_exists($key, $newInfo)) {
                // if array, match with the equivalent key & value pair in updatedData
                $this->assertArrayEqualsIntersecting($newInfo[$key][0], $value);
            }
        }

    }

    public function test_it_cannot_set_multiple_work_experience_as_current(): void
    {
        // -- Test create API --
        $individualInfo = $this->generate_test_data();

        // Generate Item and update array
        $generatedItem = Item::factory()->create();
        $individualInfo['employee']['item_id'] = $generatedItem->id;

        // Update request such that the work experience should be invalid.
        // Two work exp. with is_current_work set to true.
        $individualInfo['individual_work_experience'] =
        [
            [
                'is_current_work' => true,
                'inclusive_date_from' => '2024-01-01',
                'inclusive_date_to' => null,
                'position_title' => 'Web Developer I',
                'department_agency_office_company' => 'Test Company',
                'monthly_salary' => 30000,
                'salary_grade_id' => null,
                'status_of_appointment' => 'Permanent',
                'is_gov_service' => false,
            ],
            [
                'is_current_work' => true,
                'inclusive_date_from' => '2025-01-01',
                'inclusive_date_to' => null,
                'position_title' => 'Web Developer II',
                'department_agency_office_company' => 'Test Company',
                'monthly_salary' => 30000,
                'salary_grade_id' => null,
                'status_of_appointment' => 'Permanent',
                'is_gov_service' => false,
            ],
        ];

        // Create Individual and check for validation error
        $response = $this->withToken($this->authToken)->postJson($this->baseUri, $individualInfo);
        $response->assertStatus(422); // Should throw a Validation Error

        // -- Test update API --
        $individuals = IndividualBasicDetail::factory(5)->create();
        $firstIndividual = $individuals->first();
        $firstWorkExperience = $firstIndividual->individualWorkExperience()->first();

        // Update Work Experience
        $updateData = $this->generate_test_data('C2');
        unset($updateData['individual_eligibility']); // Only updating work exp.
        $updateData['individual_work_experience'][0]['id'] = $firstWorkExperience->id;

        array_push(
            $updateData['individual_work_experience'],
            [
                'is_current_work' => true,
                'inclusive_date_from' => '2025-01-01',
                'inclusive_date_to' => null,
                'position_title' => 'Web Developer II',
                'department_agency_office_company' => 'Test Company',
                'monthly_salary' => 30000,
                'salary_grade_id' => null,
                'status_of_appointment' => 'Permanent',
                'is_gov_service' => false,
            ]
        );

        // Should not be able to add multiple new work exp. and set multiple as new current work.
        $response = $this->withToken($this->authToken)->putJson("$this->baseUri/$firstIndividual->id", $updateData);
        $response->assertStatus(422);
    }

    public function test_it_can_select_a_different_work_experience_as_current_work(): void
    {
        $individuals = IndividualBasicDetail::factory(5)->create();
        $firstIndividual = $individuals->first();

        // Generate multiple work experiences
        $newWorkExp = IndividualWorkExperience::factory(3)->make(['is_current_work' => false])->toArray();
        $newWorkExpCurrent = IndividualWorkExperience::factory()->make()->toArray();

        $requestBody = [
            'form_type' => 'C2',
            'individual_work_experience' => array_merge($newWorkExp, [$newWorkExpCurrent]),
        ];

        // Should be able to add new work exp. and set it as the new current work.
        $response = $this->withToken($this->authToken)->putJson("$this->baseUri/$firstIndividual->id", $requestBody);
        $response->assertStatus(200);

        $curWork = IndividualWorkExperience::where('individual_basic_detail_id', '=', $firstIndividual->id)
            ->where('is_current_work', '=', true)
            ->first();

        $workCount = IndividualWorkExperience::where('individual_basic_detail_id', '=', $firstIndividual->id)->count();
        $this->assertEquals(5, $workCount); // 4 new + 1 already created

        // Should be able to change which record is the current work.
        // Get the first record where the is_current_work = false
        $selectWork = IndividualWorkExperience::where('individual_basic_detail_id', '=', $firstIndividual->id)
            ->where('is_current_work', '=', false)
            ->first();

        $requestBody = [
            'form_type' => 'C2',
            'individual_work_experience' => [
                [
                    'id' => $selectWork->id,
                    'is_current_work' => true,
                ],
            ],
        ];

        // Update
        $response = $this->withToken($this->authToken)->putJson("$this->baseUri/$firstIndividual->id", $requestBody);
        $response->assertStatus(200);

        // Should now be the new current work
        $newCurWork = IndividualWorkExperience::find($selectWork->id);
        $this->assertEquals(true, $newCurWork->is_current_work);

        // The previous current work should now be false
        $prevCurWork = IndividualWorkExperience::find($curWork->id);
        $this->assertEquals(false, $prevCurWork->is_current_work);

        // Should still only have one is_current_work as true on the database
        $countTrue = IndividualWorkExperience::where('is_current_work', '=', true)
            ->where('individual_basic_detail_id', '=', $firstIndividual->id)
            ->count();
        $this->assertEquals(1, $countTrue, 'Expected only one record with is_current_work = true, but found '.$countTrue);
    }

    public function test_it_can_add_new_work_experience_as_current_work(): void
    {
        $individuals = IndividualBasicDetail::factory(5)->create();
        $firstIndividual = $individuals->first();
        // Should have one work exp.
        $workCount = IndividualWorkExperience::where('individual_basic_detail_id', '=', $firstIndividual->id)->count();
        $this->assertEquals(1, $workCount);

        // Should only have one is_current_work as true on the database
        $countTrue = IndividualWorkExperience::where('is_current_work', '=', true)
            ->where('individual_basic_detail_id', '=', $firstIndividual->id)
            ->count();
        $this->assertEquals(1, $countTrue, 'Expected only one record with is_current_work = true, but found '.$countTrue);

        $updateData = $this->generate_test_data('C2');
        unset($updateData['individual_eligibility']); // Only updating work exp.

        // Should be able to add new work exp. and set it as the new current work.
        $response = $this->withToken($this->authToken)->putJson("$this->baseUri/$firstIndividual->id", $updateData);
        $response->assertStatus(200);
        // Should now have two work exp.
        $workCount = IndividualWorkExperience::where('individual_basic_detail_id', '=', $firstIndividual->id)->count();
        $this->assertEquals(2, $workCount);
        // Should still only have one is_current_work as true on the database
        $countTrue = IndividualWorkExperience::where('is_current_work', '=', true)
            ->where('individual_basic_detail_id', '=', $firstIndividual->id)
            ->count();
        $this->assertEquals(1, $countTrue, 'Expected only one record with is_current_work = true, but found '.$countTrue);
    }
}
