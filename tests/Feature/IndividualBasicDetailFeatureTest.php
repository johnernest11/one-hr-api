<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\ComprehensiveRecords\Employee;
use App\Models\ComprehensiveRecords\IndividualAddress;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\ComprehensiveRecords\IndividualContactInfo;
use App\Models\Item;
use App\Models\User;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Tests\TestCase;

class IndividualBasicDetailFeatureTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUri = self::BASE_API_URI.'/individual-basic-details';

    private User $user;

    private string $authToken;

    private PersistentAuthTokenManager $tokenManager;

    private array $comprehensive_records_rel = [
        'employee',
        'individualAddress',
        'individualContactInfo',
    ];

    public function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');

        $user = $this->produceUsers();
        $roles = [RoleEnum::ADMIN, RoleEnum::STANDARD_USER];
        $user->syncRoles(fake()->randomElement($roles));
        $this->user = $user; // save random user

        $this->tokenManager = resolve(PersistentAuthTokenManager::class);
        $authTokenExpiration = now()->addMinutes(config('sanctum.expiration'));
        $this->authToken = $this->tokenManager->generateToken($user, $authTokenExpiration, 'mock_token');

    }

    public static function validCreateIndividualBasicDetailsInputs(): array
    {
        $requiredFieldsOnly = [
            'individual' => [
                'first_name' => 'ppms',
                'last_name' => 'ppms',
                'middle_name' => '',
                'ext_name' => '',
                'birthday' => '2000-01-01',
                'sex' => 'male',

                'place_of_birth' => 'San Fernando',
                'civil_status' => 'Single',
                'height' => '2.0',
                'weight' => '70',
                'blood_type' => 'AB-',
                'gsis_no' => '',
                'pag_ibig_no' => '09234886',
                'philhealth_no' => '09234886',
                'sss_no' => '09234886',
                'tin' => '09234886',
                'citizenship' => 'Filipino',
                'citizenship_acquisition' => 'By Birth',
            ],

            'employee' => [
                [
                    'id_number' => '',
                    'agency_employee_no' => '',
                ],
            ],

            'individual_address' => [
                [
                    'residential_house_block_lot_no' => '#001 House',
                    'residential_street' => 'Sunshine Street',
                    'residential_subdivision_village' => '',
                    'residential_brgy_id' => 1,
                    'residential_citymun_id' => 3,
                    'residential_province_id' => 3,
                    'residential_region_id' => 2,
                    'residential_zip_code' => '3000',
                    'permanent_house_block_lot_no' => '',
                    'permanent_street' => '',
                    'permanent_subdivision_village' => '',
                    'permanent_brgy_id' => 1,
                    'permanent_citymun_id' => 3,
                    'permanent_province_id' => 3,
                    'permanent_region_id' => 2,
                    'permanent_zip_code' => '3000',

                ],
            ],

            'individual_contact_info' => [
                [
                    'tel_no' => '',
                    'mobile_no' => '+639123456789',
                    'email_address' => 'ppms.admin@test.com',
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
                [
                    'id_number' => '01111',
                    'agency_employee_no' => '01111',
                ],
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

            $input['employee'][0]['item_id'] = $generatedItem->id;
        }

        $response = $this->withToken($this->authToken)->postJson($this->baseUri, $input);
        $response->assertStatus($statusCode);

        if ($statusCode !== 422) {
            $response = $response->decodeResponseJson()['data'];
            $createdIndividual = IndividualBasicDetail::find($response['id']);

            // check if record exists
            $this->assertNotEmpty($createdIndividual);

            // check if related records are created
            foreach ($this->comprehensive_records_rel as $relationshipName) {
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

    public function test_it_can_update_individual_basic_profile(): void
    {
        $individuals = IndividualBasicDetail::factory(5)->create();
        $firstIndividual = $individuals->first();

        // Generate updated data
        $updateIndividual = IndividualBasicDetail::factory()->make()->toArray();
        $updateEmployee = Employee::factory()->make()->toArray();
        $updateAddress = IndividualAddress::factory()->make()->toArray();
        $updateContactInfo = IndividualContactInfo::factory()->make()->toArray();

        // Add the correct id on request body.
        $updateEmployee['id'] = $firstIndividual->employee->id;
        $updateAddress['id'] = $firstIndividual->individualAddress->id;
        $updateContactInfo['id'] = $firstIndividual->individualContactInfo->id;

        // Combine data and structure it so that it is similar to the request body
        $updatedData = [
            'individual' => $updateIndividual,
            'employee' => [$updateEmployee],
            'individual_address' => [$updateAddress],
            'individual_contact_nfo' => [$updateContactInfo],
        ];

        $response = $this->withToken($this->authToken)->putJson("$this->baseUri/$firstIndividual->id", $updatedData);
        $response->assertStatus(200);

    }
}
