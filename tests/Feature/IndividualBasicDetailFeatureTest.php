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
use App\Models\ComprehensiveRecords\IndividualGovernmentId;
use App\Models\ComprehensiveRecords\IndividualLnd;
use App\Models\ComprehensiveRecords\IndividualMembership;
use App\Models\ComprehensiveRecords\IndividualQuestion;
use App\Models\ComprehensiveRecords\IndividualRecognition;
use App\Models\ComprehensiveRecords\IndividualReference;
use App\Models\ComprehensiveRecords\IndividualSkillsHobby;
use App\Models\ComprehensiveRecords\IndividualVoluntaryWork;
use App\Models\ComprehensiveRecords\IndividualWorkExperience;
use App\Models\Item;
use App\Models\Libraries\Country;
use App\Models\Libraries\Division;
use App\Models\Libraries\Office;
use App\Models\Libraries\Program;
use App\Models\Libraries\SalaryGrade;
use App\Models\User;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Storage;
use Str;
use Tests\TestCase;

class IndividualBasicDetailFeatureTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUri = self::BASE_API_URI.'/individual-basic-details';

    private User $user;

    private User $userPpms;

    private User $userPas;

    private User $userStandard;

    private string $authToken;

    private string $authTokenAdmin;

    private string $authTokenPas;

    private string $authTokenStandard;

    private PersistentAuthTokenManager $tokenManager;

    private PersistentAuthTokenManager $tokenManager2;

    private array $comprehensive_records_rel = [
        'employee',
        'individualAddress',
        'individualContactInfo',
        'individualFamily',
        'individualEducationalBackground',
        'individualWorkExperience',
        'individualVoluntaryWork',
        'individualLnd',
        'individualMembership',
        'individualRecognition',
        'individualSkillsHobby',
        'individualQuestion',
        'individualReference',
        'individualGovernmentId',
    ];

    protected function setUp(): void
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

        // Simulate different user roles
        $userPpms = $this->produceUsers();
        $userPas = $this->produceUsers();
        $userStandard = $this->produceUsers();
        $roles = [RoleEnum::HR_PPMS_ADMIN, RoleEnum::HR_PAS_ADMIN, RoleEnum::STANDARD_USER];
        $userPpms->syncRoles($roles[0]);
        $userPas->syncRoles($roles[1]);
        $userStandard->syncRoles($roles[2]);
        $this->userPpms = $userPpms; // save ppms admin user
        $this->userPas = $userPas; // save pas admin user
        $this->userStandard = $userStandard; // save standard user

        $this->tokenManager2 = resolve(PersistentAuthTokenManager::class);
        $authTokenExpirationAdmin = now()->addMinutes(config('sanctum.expiration'));
        $this->authTokenAdmin = $this->tokenManager2->generateToken($userPpms, $authTokenExpirationAdmin, 'mock_token');

        $authTokenExpirationPas = now()->addMinutes(config('sanctum.expiration'));
        $this->authTokenPas = $this->tokenManager2->generateToken($userPas, $authTokenExpirationPas, 'mock_token');

        $authTokenExpirationStandard = now()->addMinutes(config('sanctum.expiration'));
        $this->authTokenStandard = $this->tokenManager2->generateToken($userStandard, $authTokenExpirationStandard, 'mock_token');

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
                    'custom_salary_grade' => '01-1',
                    'status_of_appointment' => 'Permanent',
                    'is_gov_service' => false,
                ],
            ],
            // -----> C3 starts here <-----
            'individual_voluntary_work' => [
                [
                    'is_current_org' => true,
                    'org_name' => 'Test organization 123',
                    'org_address' => 'Test organization address',
                    'from' => '2020-01-01',
                    'to' => null,
                    'number_of_hours' => 20,
                    'position_nature_of_work' => 'Admin work',
                ],
            ],
            'individual_lnd' => [
                [
                    'title' => 'Test LND 1',
                    'from' => '2021-01-01',
                    'to' => '2021-01-02',
                    'number_of_hours' => 16,
                    'type' => 'Technical',
                    'conducted_sponsor' => 'DICT',
                ],
            ],
            'individual_skills_hobby' => [
                [
                    'skill_hobby' => 'Art',
                ],
                [
                    'skill_hobby' => 'Music',
                ],
            ],
            'individual_recognition' => [
                [
                    'recognition' => 'Random Award 1',
                ],
                [
                    'recognition' => 'Random Award 2',
                ],
            ],
            'individual_membership' => [
                [
                    'association_organization' => 'Organization 1',
                ],
                [
                    'association_organization' => 'Organization 2',
                ],
                [
                    'association_organization' => 'Organization 3',
                ],
            ],

            // -----> C4 starts here <-----
            'individual_question' => [
                [
                    /* ------------------------------- Question 34 ------------------------------ */
                    'q34_a' => fake()->boolean(),
                    'q34_b' => fake()->boolean(),
                    'q34_details' => fake()->word(),
                    /* ------------------------------- Question 35 ------------------------------ */
                    'q35_a' => fake()->boolean(),
                    'q35_a_details' => fake()->word(),
                    'q35_b' => fake()->boolean(),
                    'q35_b_date_filed' => fake()->date(),
                    'q35_b_status' => fake()->word(),
                    /* ------------------------------- Question 36 ------------------------------ */
                    'q36' => fake()->boolean(),
                    'q36_details' => fake()->word(),
                    /* ------------------------------- Question 37 ------------------------------ */
                    'q37' => fake()->boolean(),
                    'q37_details' => fake()->word(),
                    /* ------------------------------- Question 38 ------------------------------ */
                    'q38_a' => fake()->boolean(),
                    'q38_a_details' => fake()->word(),
                    'q38_b' => fake()->boolean(),
                    'q38_b_details' => fake()->word(),
                    /* ------------------------------- Question 39 ------------------------------ */
                    'q39' => fake()->boolean(),
                    /* ------------------------------- Question 40 ------------------------------ */
                    'q40_a_indigenous_group' => fake()->boolean(),
                    'q40_a_details' => fake()->word(),
                    'q40_b_pwd' => fake()->boolean(),
                    'q40_b_details' => fake()->word(),
                    'q40_c_solo_parent' => fake()->boolean(),
                    'q40_c_details' => fake()->word(),
                ],
            ],
            'individual_reference' => [
                [
                    'name' => fake()->name(),
                    'address' => fake()->address(),
                    'tel_no' => fake()->numerify('+6391234567##'), // Randomizing last two digits since it is causing issues otherwise.
                ],
            ],

            'individual_government_id' => [
                'gov_issued_id' => fake()->name(),
                'gov_id_no' => fake()->bothify('ID-#######'),
                'gov_issuance' => fake()->address(),
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

            // Generate random country if individual_question is part of the input
            if (isset($input['individual_question'])) {
                $randomCountry = Country::inRandomOrder()->first()->id; // Get random country
                $input['individual_question'][0]['country_id'] = $randomCountry;
            }
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
        $initialCount = IndividualBasicDetail::count();
        $individuals = IndividualBasicDetail::factory(5)->create();

        $response = $this->withToken($this->authToken)->getJson($this->baseUri);
        $response->assertStatus(200);

        $expectedCount = $initialCount + 5;
        $response->assertJsonCount($expectedCount, 'data');
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
        $testVoluntaryWork = IndividualVoluntaryWork::factory()->make()->toArray();
        $testLnd = IndividualLnd::factory()->make()->toArray();
        $testSkillsHobby = IndividualSkillsHobby::factory()->make()->toArray();
        $testRecognition = IndividualRecognition::factory()->make()->toArray();
        $testMembership = IndividualMembership::factory()->make()->toArray();
        $testQuestion = IndividualQuestion::factory()->make()->toArray();
        $testReference = IndividualReference::factory()->make()->toArray();
        $testGovernmentId = IndividualGovernmentId::factory()->make()->toArray();

        // @todo Update as new models are added until all forms are completed
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

        $c3_request = [
            'form_type' => PDSFormType::C3->value,
            'individual_voluntary_work' => [$testVoluntaryWork],
            'individual_lnd' => [$testLnd],
            'individual_skills_hobby' => [$testSkillsHobby],
            'individual_recognition' => [$testRecognition],
            'individual_membership' => [$testMembership],
        ];

        $c4_request = [
            'form_type' => PDSFormType::C4->value,
            'individual_question' => [$testQuestion],
            'individual_reference' => [$testReference],
            'individual_government_id' => [$testGovernmentId],

        ];

        $all_request = array_merge(
            Arr::except($c1_request, 'form_type'),
            Arr::except($c2_request, 'form_type'),
            Arr::except($c3_request, 'form_type'),
            Arr::except($c4_request, 'form_type')
        );

        $requestData = match ($form_type) {
            PDSFormType::C1->value => $c1_request,
            PDSFormType::C2->value => $c2_request,
            PDSFormType::C3->value => $c3_request,
            PDSFormType::C4->value => $c4_request,
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
            if (array_key_exists($key, $newInfo['individual'])) { // assertion for individual
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

    public function test_it_can_update_c3(): void
    {
        $individuals = IndividualBasicDetail::factory(5)->create();
        $firstIndividual = $individuals->first();
        // Get first record in hasMany relationship.
        // @todo: Update as we add new models.
        $firstVoluntaryWork = $firstIndividual->individualVoluntaryWork()->first();
        $firstLnd = $firstIndividual->individualLnd()->first();
        $firstSkillsHobby = $firstIndividual->individualSkillsHobby()->first();
        $firstRecognition = $firstIndividual->individualRecognition()->first();
        $firstMembership = $firstIndividual->individualMembership()->first();

        // Generate updated data
        $newInfo = $this->generate_test_data(PDSFormType::C3->value);

        // Add the correct id on request body.
        $newInfo['individual_voluntary_work'][0]['id'] = $firstVoluntaryWork->id;
        $newInfo['individual_lnd'][0]['id'] = $firstLnd->id;
        $newInfo['individual_skills_hobby'][0]['id'] = $firstSkillsHobby->id;
        $newInfo['individual_recognition'][0]['id'] = $firstRecognition->id;
        $newInfo['individual_membership'][0]['id'] = $firstMembership->id;

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

    public function test_it_can_update_c4(): void
    {
        $individuals = IndividualBasicDetail::factory(5)->create();
        $firstIndividual = $individuals->first();

        // Get first record in relationships
        $firstReference = $firstIndividual->individualReference()->first();
        $firstGovernmentId = $firstIndividual->individualGovernmentId()->first();

        // Generate updated data
        $newInfo = $this->generate_test_data(PDSFormType::C4->value);

        // Assign the correct ids
        $newInfo['individual_question'][0]['id'] = $firstIndividual->individualQuestion->id;
        $newInfo['individual_reference'][0]['id'] = $firstReference->id;

        // Correct hasOne: use associative array, not [0]
        $newInfo['individual_government_id']['id'] = $firstGovernmentId->id;

        // Optional: random country for q39
        if (isset($newInfo['individual_question'][0]['q39']) && $newInfo['individual_question'][0]['q39']) {
            $newInfo['individual_question'][0]['country_id'] = Country::inRandomOrder()->first()->id;
        }

        // Update via API
        $response = $this->withToken($this->authToken)
            ->putJson("$this->baseUri/{$firstIndividual->id}", $newInfo);
        $response->assertStatus(200);

        $responseData = $response->decodeResponseJson()['data'];

        // Assert updated fields
        foreach ($newInfo as $key => $value) {
            if (is_array($value) && array_key_exists($key, $responseData)) {
                $this->assertArrayEqualsIntersecting(
                    // For hasOne relationships, just pass the associative array
                    $key === 'individual_government_id' ? $value : $value[0],
                    $responseData[$key]
                );
            }
        }
    }

    public function test_it_cannot_create_more_than_3_references(): void
    {
        $individualInfo = $this->generate_test_data();

        // Generate Item and update array
        $generatedItem = Item::factory()->create();
        $individualInfo['employee']['item_id'] = $generatedItem->id;

        // Update request such that the references will be more than 3
        // There will already be 1 reference in $individualInfo
        $individualInfo['individual_reference'] =
        [
            [
                'name' => fake()->name(),
                'address' => fake()->address(),
                'tel_no' => fake()->numerify('+6391234567##'), // Randomizing last two digits since it is causing issues otherwise.
            ],
            [
                'name' => fake()->name(),
                'address' => fake()->address(),
                'tel_no' => fake()->numerify('+6391234567##'), // Randomizing last two digits since it is causing issues otherwise.
            ],
            [
                'name' => fake()->name(),
                'address' => fake()->address(),
                'tel_no' => fake()->numerify('+6391234567##'), // Randomizing last two digits since it is causing issues otherwise.
            ],
            [
                'name' => fake()->name(),
                'address' => fake()->address(),
                'tel_no' => fake()->numerify('+6391234567##'), // Randomizing last two digits since it is causing issues otherwise.
            ],
        ];

        // Create Individual and check for validation error
        $response = $this->withToken($this->authToken)->postJson($this->baseUri, $individualInfo);
        $response->assertStatus(422); // Should throw a Validation Error
    }

    public function test_it_can_add_new_reference_max_of_3(): void
    {
        $individuals = IndividualBasicDetail::factory(5)->create();
        $firstIndividual = $individuals->first();
        // Should have one reference
        $refCount = IndividualReference::where('individual_basic_detail_id', '=', $firstIndividual->id)->count();
        $this->assertEquals(1, $refCount);

        $updateData = [
            'form_type' => PDSFormType::C4->value,
            'individual_reference' => IndividualReference::factory(3)->make()->toArray(),
        ];

        // Should throw an error when attempting to create new references since the total count of the records will be 4.
        $response = $this->withToken($this->authToken)->putJson("$this->baseUri/$firstIndividual->id", $updateData);
        $response->assertStatus(403); // Should be an UNAUTHORIZED_ERROR

        $newUpdateData = [
            'form_type' => PDSFormType::C4->value,
            'individual_reference' => IndividualReference::factory(2)->make()->toArray(),
        ];

        // Should now proceed with creating new references since the total count of records will be 3.
        $response = $this->withToken($this->authToken)->putJson("$this->baseUri/$firstIndividual->id", $newUpdateData);
        $response->assertStatus(200);

        // Should now have 3 references
        $newRefCount = IndividualReference::where('individual_basic_detail_id', '=', $firstIndividual->id)->count();
        $this->assertEquals(3, $newRefCount);
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
                'custom_salary_grade' => '01-1',
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
                'custom_salary_grade' => '01-1',
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
                'custom_salary_grade' => '01-1',
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

    public function test_it_can_update_item_status(): void
    {
        // Test that upon creation, the item status will change to filled.
        $individualInfo = $this->generate_test_data();

        // Generate Item and update array
        $generatedItem = Item::factory()->create();
        $individualInfo['employee']['item_id'] = $generatedItem->id;
        // The default status of the generated item should be unfilled and with no date_filled_up
        $this->assertEquals('Unfilled', $generatedItem->status->value);
        $this->assertNull($generatedItem->date_filled_up);

        // Create Individual
        $response = $this->withToken($this->authToken)->postJson($this->baseUri, $individualInfo);
        $response->assertStatus(201);

        // The item should now be filled and with date_filled_up
        $newItem = Item::find($generatedItem->id);
        $dateFilled = Carbon::now()->toDateString();
        $this->assertEquals('Filled', $newItem->status->value);
        $this->assertEquals($dateFilled, $newItem->date_filled_up->toDateString());

        // Test that upon update, the old item status should be unfilled + date_filled_up is null
        // and new item status is filled + with date_filled_up as date now

        // Generate updated data
        $individualId = $response->decodeResponseJson()['data']['id'];
        $individual = IndividualBasicDetail::find($individualId);
        $newInfo = $this->generate_test_data(PDSFormType::C1->value);

        // Change the item of the employee.
        $updatedItem = Item::factory()->create();
        $newInfo['employee']['id'] = $individual->employee->id;
        $newInfo['employee']['item_id'] = $updatedItem->id;

        $newInfo = Arr::only($newInfo, ['individual', 'employee', 'form_type']);

        // Update
        $response = $this->withToken($this->authToken)->putJson("$this->baseUri/$individual->id", $newInfo);
        $response->assertStatus(200);

        // The item of the employee should now be equal to the updatedItem
        // with the old item reset back to unfilled and the new one to
        // filled and with date_filled_up
        $oldItem = Item::find($newItem->id);
        $this->assertEquals('Unfilled', $oldItem->status->value);
        $this->assertNull($oldItem->date_filled_up);

        $newlyUpdatedItem = Item::find($updatedItem->id);
        $this->assertEquals('Filled', $newlyUpdatedItem->status->value);
        $this->assertEquals(Carbon::now()->toDateString(), $newlyUpdatedItem->date_filled_up->toDateString());

    }

    public function test_ppms_admin_can_create_records(): void
    {
        $initialCount = IndividualBasicDetail::count();
        $individualInfo = $this->generate_test_data(PDSFormType::C1->value);

        $generatedItem = Item::factory()->create();
        $individualInfo['employee']['item_id'] = $generatedItem->id;
        unset($individualInfo['form_type']);

        // Assert that PPMS admin should be able to create a record
        $response = $this->withToken($this->authTokenAdmin)->postJson($this->baseUri, $individualInfo); // Use the generated token for ppms admin
        $response->assertStatus(201); // Should be able to create
        $this->assertDatabaseCount('individual_basic_details', $initialCount + 1);

    }

    public function test_ppms_admin_can_update_records(): void
    {
        $individuals = IndividualBasicDetail::factory(5)->create();
        $firstIndividual = $individuals->first();

        // Get first record in hasMany relationship.
        $firstReference = $firstIndividual->individualReference()->first();
        $firstGovermentId = $firstIndividual->individualGovernmentId()->first();

        // Generate updated data
        $newInfo = $this->generate_test_data(PDSFormType::C4->value);

        // Add the correct id on request body.
        $newInfo['individual_question'][0]['id'] = $firstIndividual->individualQuestion->id;
        $newInfo['individual_reference'][0]['id'] = $firstReference->id;
        $newInfo['individual_government_id'][0]['id'] = $firstGovermentId->id;

        // Generate random countries if individual_question is part of the input and q39 is true
        if (isset($newInfo['individual_question']) and $newInfo['individual_question'][0]['q39']) {
            $randomCountry = Country::inRandomOrder()->first()->id; // Get random country
            $newInfo['individual_question'][0]['country_id'] = $randomCountry;
        }

        // Can Update
        $response = $this->withToken($this->authTokenAdmin)->putJson("$this->baseUri/$firstIndividual->id", $newInfo);
        $response->assertStatus(200);
    }

    public function test_ppms_admin_can_view_records(): void
    {
        $individual = IndividualBasicDetail::factory()->create();
        $find_individual_data = IndividualBasicDetail::find($individual->id);

        $response = $this->withToken($this->authTokenAdmin)->getJson("$this->baseUri/$find_individual_data->id");
        $response->assertStatus(200);

    }

    public function test_pas_admin_cannot_create_records(): void
    {
        $initialIndividualsCount = IndividualBasicDetail::count();
        $individualInfo = $this->generate_test_data(PDSFormType::C1->value);

        $generatedItem = Item::factory()->create();
        $individualInfo['employee']['item_id'] = $generatedItem->id;
        unset($individualInfo['form_type']);

        // Assert that PAS admin should not be able to create a record
        $response = $this->withToken($this->authTokenPas)->postJson($this->baseUri, $individualInfo); // Use the generated token for PAS user
        $response->assertStatus(403); // Should be 403 Forbidden (UNAUTHORIZED_ERROR)
        $this->assertDatabaseCount('individual_basic_details', $initialIndividualsCount);
    }

    public function test_pas_admin_can_update_records(): void
    {
        $individuals = IndividualBasicDetail::factory(5)->create();
        $firstIndividual = $individuals->first();

        // Get first record in hasMany relationship.
        $firstReference = $firstIndividual->individualReference()->first();

        // Generate updated data
        $newInfo = $this->generate_test_data(PDSFormType::C4->value);

        // Add the correct id on request body.
        $newInfo['individual_question'][0]['id'] = $firstIndividual->individualQuestion->id;
        $newInfo['individual_reference'][0]['id'] = $firstReference->id;

        // Generate random countries if individual_question is part of the input and q39 is true
        if (isset($newInfo['individual_question']) and $newInfo['individual_question'][0]['q39']) {
            $randomCountry = Country::inRandomOrder()->first()->id; // Get random country
            $newInfo['individual_question'][0]['country_id'] = $randomCountry;
        }

        // Can Update
        $response = $this->withToken($this->authTokenPas)->putJson("$this->baseUri/$firstIndividual->id", $newInfo);
        $response->assertStatus(200);
    }

    public function test_pas_admin_can_view_records(): void
    {
        $individual = IndividualBasicDetail::factory()->create();
        $find_individual_data = IndividualBasicDetail::find($individual->id);

        $response = $this->withToken($this->authTokenPas)->getJson("$this->baseUri/$find_individual_data->id");
        $response->assertStatus(200);

    }

    public function test_standard_user_cannot_create_records(): void
    {
        $initialIndividualsCount = IndividualBasicDetail::count();
        $individualInfo = $this->generate_test_data(PDSFormType::C1->value);

        $generatedItem = Item::factory()->create();
        $individualInfo['employee']['item_id'] = $generatedItem->id;
        unset($individualInfo['form_type']);

        // Assert that Standard User should not be able to create a record
        $response = $this->withToken($this->authTokenStandard)->postJson($this->baseUri, $individualInfo); // Use the generated token for standard user
        $response->assertStatus(403); // Should be 403 Forbidden (UNAUTHORIZED_ERROR)
        $this->assertDatabaseCount('individual_basic_details', $initialIndividualsCount);

    }

    public function test_standard_user_can_only_update_own_records(): void
    {
        // Generate a data that the current user does not own
        $notOwnData = IndividualBasicDetail::factory()->withExistingUserProfile()->create();

        // Generate updated data
        $newInfo = $this->generate_test_data(PDSFormType::C4->value);

        // Add the correct id on request body.
        $newInfo['individual_question'][0]['id'] = $notOwnData->individualQuestion->id;
        unset($newInfo['individual_reference']);
        $newInfo['individual_government_id'][0]['id'] = $notOwnData->individualGovernmentId()->first()->id;

        // Generate random countries if individual_question is part of the input and q39 is true
        if (isset($newInfo['individual_question']) and $newInfo['individual_question'][0]['q39']) {
            $randomCountry = Country::inRandomOrder()->first()->id; // Get random country
            $newInfo['individual_question'][0]['country_id'] = $randomCountry;
        }

        // Should not be able to update the record
        $response = $this->withToken($this->authTokenStandard)->putJson("$this->baseUri/$notOwnData->id", $newInfo);

        $response->assertStatus(403);

        // Generate data with the current user as the owner
        $stndrdUserProf = $this->userStandard->userProfile;
        $ownData = IndividualBasicDetail::factory()->withExistingUserProfile($stndrdUserProf)->create();
        $newInfo['individual_question'][0]['id'] = $ownData->individualQuestion->id; // Update id to point to the correct data
        $newInfo['individual_government_id'][0]['id'] = $ownData->individualGovernmentId->first()->id;

        // Should now be able to update
        $response = $this->withToken($this->authTokenStandard)->putJson("$this->baseUri/$ownData->id", $newInfo);
        $response->assertStatus(200);
    }

    public function test_standard_user_can_only_view_own_records(): void
    {

        $notOwnData = IndividualBasicDetail::factory()->withExistingUserProfile()->create();

        // Cannot view
        $response = $this->withToken($this->authTokenStandard)->getJson("$this->baseUri/$notOwnData->id");
        $response->assertStatus(403);

        // Generate data with the current user as the owner
        $stndrdUserProf = $this->userStandard->userProfile;
        $ownData = IndividualBasicDetail::factory()->withExistingUserProfile($stndrdUserProf)->create();

        // Can View
        $response = $this->withToken($this->authTokenStandard)->getJson("$this->baseUri/$ownData->id");
        $response->assertStatus(200);
    }

    public function test_it_can_import_pds_excel(): void
    {
        // Importing will now not generate a record. Instead it will read the file and return a response of the mapped data.
        // Assert that the databases are empty before importing
        $initialIndividualsCount = IndividualBasicDetail::count();
        $initialEmployeesCount = Employee::count();
        $this->assertDatabaseCount('individual_basic_details', $initialIndividualsCount);
        $this->assertDatabaseCount('employees', $initialEmployeesCount);

        // Process test file
        $testExcelPath = Storage::disk('assets')->path('test_data_pds.xlsx');

        // Get the file name and mime type for the UploadedFile constructor
        $filename = basename($testExcelPath);
        $mimeType = mime_content_type($testExcelPath);
        $error = null; // No upload error
        $test = true; // Mark as a test file

        // Create an UploadedFile instance
        $uploadedFile = new UploadedFile($testExcelPath, $filename, $mimeType, $error, $test);

        // Generate sample data for the employee
        $division = Division::first();
        $section = $division->sectionOrUnits()->first();

        // Payload
        $payload = [
            'excel_file' => $uploadedFile,
            'is_update' => 0, // Set as false since we are creating one.
            'employee_id' => null, // Set as null since we are creating one.
            'id_number' => (string) fake()->randomNumber(9),
            'item_id' => Item::factory()->create()->id,
            'salary_grade_id' => SalaryGrade::first()->id,
            'program_id' => Program::first()->id,
            'office_id' => Office::first()->id,
            'division_id' => $division->id,
            'section_or_unit_id' => $section->id,
            'agency_employee_no' => (string) fake()->randomNumber(9),
        ];

        $response = $this->withToken($this->authTokenStandard)->postJson("$this->baseUri/import", $payload);
        $response->assertStatus(200);

        // Assert that the databases are still empty after importing since we are now previewing the mapped data.
        $this->assertDatabaseCount('individual_basic_details', $initialIndividualsCount);
        $this->assertDatabaseCount('employees', $initialEmployeesCount);
    }
}
