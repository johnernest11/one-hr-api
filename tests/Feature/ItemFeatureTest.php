<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Enums\Role as RoleEnum;
use App\Models\Item;
use App\Models\User;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use ConversionHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
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

    public function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');

        $user_ppms = $this->produceUsers();
        $user_standard = $this->produceUsers();
        $roles = [RoleEnum::HR_PPMS_ADMIN, RoleEnum::STANDARD_USER];
        $user_ppms->syncRoles($roles[0]);
        $user_standard->syncRoles($roles[1]);
        $this->user_ppms = $user_ppms; // save ppms admin user
        $this->user_standard = $user_ppms; // save standard user

        $this->tokenManager = resolve(PersistentAuthTokenManager::class);
        $authTokenExpirationAdmin = now()->addMinutes(config('sanctum.expiration'));
        $this->authTokenAdmin = $this->tokenManager->generateToken($user_ppms, $authTokenExpirationAdmin, 'mock_token');

        $authTokenExpirationStandard = now()->addMinutes(config('sanctum.expiration'));
        $this->authTokenStandard = $this->tokenManager->generateToken($user_standard, $authTokenExpirationStandard, 'mock_token');
    }

    public static function validCreateItemInputs(): array
    {
        $requiredFieldsOnly = [
            'number' => 'FO1-COS-CPIII-000999',
            'date_of_creation' => '2025-04-03',
            'status' => 'Unfilled',
            'employment_status' => 'Contract of Service',
            'position_id' => 1,
            'fund_source_id' => 12,
        ];

        $allFields = [
            'number' => 'FO1-COS-CPIII-000999',
            'date_of_creation' => '2025-04-03',
            'status' => 'Unfilled',
            'date_filled_up' => '2025-04-03',
            'employment_status' => 'Contract of Service',
            'position_id' => 1,
            'fund_source_id' => 12,

        ];

        $missingRequiredFields = Arr::only(
            $allFields,
            'date_filled_up'
        );

        return [
            [$requiredFieldsOnly, 201],
            [$allFields, 201],
            [$missingRequiredFields, 422],
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
        $items = Item::factory(5)->create();

        $response = $this->withToken($this->authTokenAdmin)->getJson("$this->baseUri");
        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
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
            'number' => fake()->regexify('[A-Z]{3}-[A-Z]{3}-[A-Z]{3}-\d{6}'), // Simulate number format from the provided database
            'date_of_creation' => fake()->date(),
            'status' => 'Filled',
            'date_filled_up' => fake()->date(),
            'employment_status' => fake()->randomElement(ConversionHelper::enumToArray(EmploymentStatus::class)),
            'position_id' => 1,
            'fund_source_id' => 12,
        ]; // update status to done

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
            'number' => 'FO1-COS-CPIII-000999',
            'date_of_creation' => '2025-04-03',
            'status' => 'Filled',
            'date_filled_up' => '2025-04-03',
            'employment_status' => 'Contract of Service',
            'position_id' => 1,
            'fund_source_id' => 12,
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
            'number' => '001-203294',
            'date_of_creation' => '2025-04-03',
            'status' => 'Unfilled',
            'date_filled_up' => '2025-04-03',
            'employment_status' => 'Contract of Service',
            'position_id' => 1,
            'fund_source_id' => 12,
        ];

        $response = $this->withToken($this->authTokenStandard)->postJson($this->baseUri, $testInput);
        $response->assertStatus(403);
    }

    public function test_standard_user_cannot_access_update_endpoint(): void
    {
        $items = Item::factory(5)->create();
        $firstItem = $items->first();

        $updatedData = [
            'number' => '001-999999',
            'date_of_creation' => '2025-04-03',
            'status' => 'Filled',
            'date_filled_up' => '2025-04-03',
            'employment_status' => 'Contract of Service',
            'position_id' => 1,
            'fund_source_id' => 12,
        ]; // update status to done

        $response = $this->withToken($this->authTokenStandard)->putJson("$this->baseUri/$firstItem->id", $updatedData);
        $response->assertStatus(403);
    }
}
