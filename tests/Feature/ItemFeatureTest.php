<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\Item;
use App\Models\User;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemFeatureTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUri = self::BASE_API_URI.'/items';

    private User $user_ppms;

    private User $user_standard;

    private string $authTokenAdmin;

    private string $authTokenStandard;

    private PersistentAuthTokenManager $tokenManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');

        $user_ppms = $this->produceUsers();
        $user_standard = $this->produceUsers();
        $roles = [RoleEnum::HR_PPMS_ADMIN, RoleEnum::STANDARD_USER];
        $user_ppms->syncRoles($roles[0]);
        $user_standard->syncRoles($roles[1]);
        $this->user_ppms = $user_ppms; // save ppms admin user
        $this->user_standard = $user_standard; // save standard user

        $this->tokenManager = resolve(PersistentAuthTokenManager::class);
        $authTokenExpirationAdmin = now()->addMinutes(config('sanctum.expiration'));
        $this->authTokenAdmin = $this->tokenManager->generateToken($user_ppms, $authTokenExpirationAdmin, 'mock_token');

        $authTokenExpirationStandard = now()->addMinutes(config('sanctum.expiration'));
        $this->authTokenStandard = $this->tokenManager->generateToken($user_standard, $authTokenExpirationStandard, 'mock_token');
    }

    public static function validCreateItemInputs(): array
    {
        // Required Fields Dataset (Valid)
        $requiredFieldsOnly = [
            'number' => 'FO1-COS-CPIII-000999',
            'date_of_creation' => '2025-04-03',
            'status' => 'Unfilled',
            'employment_status' => 'Contract of Service',

            // Required Foreign Keys
            'division_id' => 1,
            'section_or_unit_id' => 1,
            'office_id' => 1,
            'position_id' => 1,
            'fund_source_id' => 12,
            'salary_grade_id' => 1,
        ];

        // All Fields Dataset (Valid)
        $allFields = [
            // Core Identity & Status
            'number' => 'FO1-COS-CPIII-000888',
            'date_of_creation' => '2025-04-03',
            'item_classification' => 'Technical',
            'status' => 'Unfilled',
            'date_filled_up' => '2025-04-03',
            'employment_status' => 'Contract of Service',

            // Organization Data (Foreign Keys)
            'division_id' => 1,
            'section_or_unit_id' => 1,
            'program_id' => 1,
            'office_id' => 1,
            'psipop_id' => 1,

            // Compensation & Relationships
            'position_id' => 1,
            'salary_grade_id' => 1,
            'fund_source_id' => 12,

            // Designation and Assignment Details
            'designation' => 'Project Development Officer III',
            'date_of_designation' => '2025-04-03',
            'special_order_number' => 'SO-2026-1042',

            // Position History and Vacancy Tracking
            'mode_of_accession' => 'Original Appointment',
            'history_of_position' => 'Transferred from Region 1 focal unit to division office.',
            'former_incumbent' => 'John Doe',
            'mode_of_separation' => 'Resignation',
            'date_of_vacant' => '2025-03-03',
            'remarks_of_vacancy' => 'Position became vacant due to migration abroad.',
            'status_of_vacant_position' => 'For Advertisement',
            'remarks' => 'Priority item for upcoming hiring block cycle.',
        ];

        // Explicitly Bad Dataset (Invalid - missing number, division_id, date_of_creation, etc.)
        $missingRequiredFields = [
            'date_filled_up' => '2025-04-03',
            'item_classification' => 'Technical',
        ];

        return [
            'Required Fields Only (Valid)' => [$requiredFieldsOnly, 201],
            'All Fields Formatted (Valid)' => [$allFields, 201],
            'Missing Required Schema (Invalid)' => [$missingRequiredFields, 422],
        ];
    }

    /**
     * @dataProvider validCreateItemInputs
     *
     * @note we can't use Eloquent nor faker in data providers
     *
     * @throws Throwable
     */
    public function test_it_can_create_an_item($input, $statusCode): void
    {

        $response = $this->withToken($this->authTokenAdmin)->postJson($this->baseUri, $input);
        $response->assertStatus($statusCode);

        if ($statusCode !== 422) {
            $response = $response->decodeResponseJson()['data'];
            $createdItem = Item::find($response['id']);

            // check if record exists
            $this->assertNotEmpty($createdItem);
        }
    }

    public function test_it_can_read_all_items(): void
    {
        $existingCount = Item::count();
        $items = Item::factory(5)->create();

        $response = $this->withToken($this->authTokenAdmin)->getJson("$this->baseUri");
        $response->assertStatus(200);

        $responseData = $response->decodeResponseJson()['data'];
        $this->assertCount($existingCount + 5, $responseData);
    }

    public function test_it_can_read_item_by_id(): void
    {
        $item = Item::factory()->create();
        $find_item = Item::find($item->id);

        $response = $this->withToken($this->authTokenAdmin)->getJson("$this->baseUri/$find_item->id");
        $response->assertStatus(200);
    }

    public function test_it_can_update_item(): void
    {
        $items = Item::factory(5)->create();
        $firstItem = $items->first();

        $updatedData = [
            // Core Identity & Details
            'number' => fake()->regexify('[A-Z]{3}-[A-Z]{3}-[A-Z]{3}-\d{6}'),
            'date_of_creation' => fake()->date(),
            'item_classification' => fake()->randomElement(['Key Positions', 'Technical', 'Support to Technical', 'Administrative']),

            // Organization Data (Foreign Keys)
            'division_id' => 2, // Incremented IDs to simulate a real data change update
            'section_or_unit_id' => 2,
            'program_id' => 2,
            'office_id' => 2,
            'psipop_id' => 2,

            // Compensation & Employment Details
            'employment_status' => 'Contract of Service',
            'salary_grade_id' => 2, // Aligned with your updated migration key!
            'fund_source_id' => 14,
            'position_id' => 2,

            // Designation Details
            'designation' => fake()->word().' Supervisor',
            'date_of_designation' => fake()->date(),
            'special_order_number' => 'SO-'.fake()->year().'-5512',

            // Position History and Vacancy Tracking
            'status' => 'Filled', // Explicitly marked as filled
            'mode_of_accession' => fake()->randomElement(['Promotion', 'Transfer']),
            'date_filled_up' => fake()->date(),
            'history_of_position' => fake()->sentence(),
            'former_incumbent' => fake()->name(),
            'mode_of_separation' => fake()->randomElement(['Resignation', 'Retirement']),
            'date_of_vacant' => fake()->date(),
            'remarks_of_vacancy' => fake()->sentence(),
            'status_of_vacant_position' => 'Filled',
            'direct_contact_exposure_with_client' => fake()->randomElement(['Yes', 'No', 'Occasional']),
            'remarks' => fake()->paragraph(),
        ];

        $response = $this->withToken($this->authTokenAdmin)->putJson("$this->baseUri/$firstItem->id", $updatedData);
        $response->assertStatus(200);

        $response = $response->decodeResponseJson()['data'];

        $this->assertEquals($response['status'], $updatedData['status']); // status should now be done

    }

    public function test_it_can_search_item(): void
    {
        $items = Item::factory(5)->create();
        $firstItem = $items->first();

        // Update the number for easier search
        $updatedData = [
            // Core Identity & Status
            'number' => 'FO1-COS-CPIII-000999',
            'date_of_creation' => '2025-04-03',
            'item_classification' => 'Technical',
            'status' => 'Filled', // Simulating an item status update to Filled
            'date_filled_up' => '2025-04-03',
            'employment_status' => 'Contract of Service',

            // Organization Data (Foreign Keys)
            'division_id' => 1,
            'section_or_unit_id' => 1,
            'program_id' => 1,
            'office_id' => 1,
            'psipop_id' => 1,

            // Compensation & Relationships
            'position_id' => 1,
            'salary_grade_id' => 1, // Aligned with your updated migration!
            'fund_source_id' => 12,

            // Designation and Assignment Details
            'designation' => 'Project Development Officer III',
            'date_of_designation' => '2025-04-10',
            'special_order_number' => 'SO-2025-1042',

            // Position History and Vacancy Tracking
            'mode_of_accession' => 'Original Appointment',
            'history_of_position' => 'Transferred from Region 1 focal unit to division office.',
            'former_incumbent' => 'John Doe',
            'mode_of_separation' => 'Resignation',
            'date_of_vacant' => '2025-05-01',
            'remarks_of_vacancy' => 'Position became vacant due to migration abroad.',
            'status_of_vacant_position' => 'Filled',
            'direct_contact_exposure_with_client' => 'Yes',
            'remarks' => 'Priority item update completed.',
        ];

        $response = $this->withToken($this->authTokenAdmin)->putJson("$this->baseUri/$firstItem->id", $updatedData);
        $response->assertStatus(200);

        // Search by number
        $q = 'CPIII';
        $response = $this->withToken($this->authTokenAdmin)->getJson("$this->baseUri/search?query=$q");
        $response->assertStatus(200);

        $responseData = $response->decodeResponseJson()['data'];

        // Check if the search returned any results
        $this->assertNotEmpty($responseData, 'Search returned no results.');

        // Access the first item in the search results
        $firstSearchResult = $responseData[0];

        // Assert that the result should have the query
        $this->assertStringContainsString($q, $firstSearchResult['number']);

    }

    public function test_standard_user_cannot_access_view_endpoint(): void
    {
        $items = Item::factory(5)->create();

        $response = $this->withToken($this->authTokenStandard)->getJson("$this->baseUri");
        $response->assertStatus(403);
    }

    public function test_standard_user_cannot_access_create_endpoint(): void
    {

        $testInput = [
            // Core Identity & Status
            'number' => '001-203294',
            'date_of_creation' => '2025-04-03',
            'item_classification' => 'Technical',
            'status' => 'Unfilled',
            'date_filled_up' => '2025-04-03',
            'employment_status' => 'Contract of Service',

            // Organization Data (Foreign Keys)
            'division_id' => 1,
            'section_or_unit_id' => 1,
            'program_id' => 1,
            'office_id' => 1,
            'psipop_id' => 1,

            // Compensation & Relationships
            'position_id' => 1,
            'salary_grade_id' => 1, // Matches your migration's salary_grade_id field
            'fund_source_id' => 12,

            // Designation and Assignment Details
            'designation' => 'Project Development Officer III',
            'date_of_designation' => '2025-04-10',
            'special_order_number' => 'SO-2025-1042',

            // Position History and Vacancy Tracking
            'mode_of_accession' => 'Original Appointment',
            'history_of_position' => 'Transferred from Region 1 focal unit to division office.',
            'former_incumbent' => 'John Doe',
            'mode_of_separation' => 'Resignation',
            'date_of_vacant' => '2025-05-01',
            'remarks_of_vacancy' => 'Position became vacant due to migration abroad.',
            'status_of_vacant_position' => 'For Advertisement',
            'direct_contact_exposure_with_client' => 'Yes',
            'remarks' => 'Priority item for upcoming hiring block cycle.',
        ];

        $response = $this->withToken($this->authTokenStandard)->postJson($this->baseUri, $testInput);
        $response->assertStatus(403);
    }

    public function test_standard_user_cannot_access_update_endpoint(): void
    {
        $items = Item::factory(5)->create();
        $firstItem = $items->first();

        $updatedData = [
            // Core Identity & Status
            'number' => '001-999999',
            'date_of_creation' => '2025-04-03',
            'item_classification' => 'Technical',
            'status' => 'Filled', // Updated status block
            'date_filled_up' => '2025-04-03',
            'employment_status' => 'Contract of Service',

            // Organization Data (Foreign Keys)
            'division_id' => 1,
            'section_or_unit_id' => 1,
            'program_id' => 1,
            'office_id' => 1,
            'psipop_id' => 1,

            // Compensation & Relationships
            'position_id' => 1,
            'salary_grade_id' => 1, // Matches your migration's salary_grade_id field
            'fund_source_id' => 12,

            // Designation and Assignment Details
            'designation' => 'Project Development Officer III',
            'date_of_designation' => '2025-04-10',
            'special_order_number' => 'SO-2025-1042',

            // Position History and Vacancy Tracking
            'mode_of_accession' => 'Original Appointment',
            'history_of_position' => 'Transferred from Region 1 focal unit to division office.',
            'former_incumbent' => 'John Doe',
            'mode_of_separation' => 'Resignation',
            'date_of_vacant' => '2025-05-01',
            'remarks_of_vacancy' => 'Position became vacant due to migration abroad.',
            'status_of_vacant_position' => 'Filled',
            'direct_contact_exposure_with_client' => 'Yes',
            'remarks' => 'Priority item update completed.',
        ];

        $response = $this->withToken($this->authTokenStandard)->putJson("$this->baseUri/$firstItem->id", $updatedData);
        $response->assertStatus(403);
    }
}
