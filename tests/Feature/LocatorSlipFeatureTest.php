<?php

namespace Tests\Feature;

use App\Enums\ApprovalType;
use App\Enums\LocatorFormType;
use App\Enums\Role as RoleEnum;
use App\Models\ComprehensiveRecords\Employee;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\LocatorSlip\LocatorSlip;
use App\Models\LocatorSlip\LocatorSlipLogger;
use App\Models\User;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use ConversionHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocatorSlipFeatureTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUri = self::BASE_API_URI.'/employees/locator-slips';

    private string $uriWithId = self::BASE_API_URI.'/employees';

    private User $user_admin;

    private string $authTokenAdmin;

    private PersistentAuthTokenManager $tokenManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');

        $user_admin = $this->produceUsers();
        $roles = [RoleEnum::ADMIN];
        $user_admin->syncRoles($roles[0]);
        $this->user_admin = $user_admin;

        $this->tokenManager = resolve(PersistentAuthTokenManager::class);
        $authTokenExpirationAdmin = now()->addMinutes(config('sanctum.expiration'));
        $this->authTokenAdmin = $this->tokenManager->generateToken($user_admin, $authTokenExpirationAdmin, 'mock_token');
    }

    public function test_it_can_view_employees_locator_slip(): void
    {
        $individual = IndividualBasicDetail::factory()->create();
        $employee = Employee::whereBelongsTo($individual)->firstOrFail();

        $locatorSlips = LocatorSlip::factory(5)->setEmployee($employee)->create();

        $response = $this->withToken($this->authTokenAdmin)->getJson($this->uriWithId.'/'.$employee->id.'/locator-slips');
        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
    }

    public function test_it_can_view_grouped_locator_slip(): void
    {
        $individual_1 = IndividualBasicDetail::factory()->create();
        $employee_1 = Employee::whereBelongsTo($individual_1)->firstOrFail();

        $individual_2 = IndividualBasicDetail::factory()->create();
        $employee_2 = Employee::whereBelongsTo($individual_2)->firstOrFail();

        $locatorSlips_1 = LocatorSlip::factory(5)->setEmployee($employee_1)->create();
        $locatorSlips_2 = LocatorSlip::factory(5)->setEmployee($employee_2)->create();

        $response = $this->withToken($this->authTokenAdmin)->getJson($this->baseUri.'/'.'grouped');
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data'); // this will be the two employees
    }

    public function test_it_can_view_locator_by_id(): void
    {
        $individual = IndividualBasicDetail::factory()->create();
        $employee = Employee::whereBelongsTo($individual)->firstOrFail();

        $locatorSlips = LocatorSlip::factory(5)->setEmployee($employee)->create();

        $response = $this->withToken($this->authTokenAdmin)->getJson($this->baseUri.'/'.$locatorSlips->first()->id);
        $response->assertStatus(200);
    }

    public function test_it_can_create_locator_slip(): void
    {
        $individual = IndividualBasicDetail::factory()->create();
        $employee = Employee::whereBelongsTo($individual)->firstOrFail();

        $this->assertDatabaseCount('locator_slips', 0);

        $payload = [
            'form_type' => fake()->randomElement(ConversionHelper::enumToArray(LocatorFormType::class)),
        ];

        $response = $this->withToken($this->authTokenAdmin)->postJson($this->uriWithId.'/'.$employee->id.'/'.'locator-slips', $payload);
        $response->assertStatus(201);
        $this->assertDatabaseCount('locator_slips', 1);
    }

    public function test_it_can_limit_locator_slip_creation(): void
    {
        $individual = IndividualBasicDetail::factory()->create();
        $employee = Employee::whereBelongsTo($individual)->firstOrFail();

        $this->assertDatabaseCount('locator_slips', 0);

        $formC = [
            'form_type' => LocatorFormType::FORM_C->value,
        ];

        $formA = [
            'form_type' => LocatorFormType::FORM_A->value,
        ];

        // The employee can only have one form A at a time.
        $response = $this->withToken($this->authTokenAdmin)->postJson($this->uriWithId.'/'.$employee->id.'/'.'locator-slips', $formA);
        $response->assertStatus(201);
        $this->assertDatabaseCount('locator_slips', 1);

        // This should throw an error.
        $response = $this->withToken($this->authTokenAdmin)->postJson($this->uriWithId.'/'.$employee->id.'/'.'locator-slips', $formA);
        $response->assertStatus(400); // BAD REQUEST
        $this->assertDatabaseCount('locator_slips', 1); // Should still be 1

        // The employee can only have one form C per period.
        $response = $this->withToken($this->authTokenAdmin)->postJson($this->uriWithId.'/'.$employee->id.'/'.'locator-slips', $formC);
        $response->assertStatus(201);
        $this->assertDatabaseCount('locator_slips', 2); // Should now be 2

        // This should throw an error.
        $response = $this->withToken($this->authTokenAdmin)->postJson($this->uriWithId.'/'.$employee->id.'/'.'locator-slips', $formC);
        $response->assertStatus(400);
        $this->assertDatabaseCount('locator_slips', 2); // Should still be 2
    }

    public function test_it_can_create_new_locator_slip_logs(): void
    {
        $individual = IndividualBasicDetail::factory()->create();
        $employee = Employee::whereBelongsTo($individual)->firstOrFail();

        $date = now()->toDateString();
        $locatorSlip = LocatorSlip::factory()->create(['employee_id' => $employee->id, 'date' => $date]);

        $createNewLogs = [
            'locator_slip_logger' => [
                [
                    'date' => $date,
                    'time_out' => null,
                    'time_in' => null,
                    'destination' => fake()->text(),
                    'purpose' => fake()->text(),
                    'approved_for' => fake()->randomElement(ConversionHelper::enumToArray(ApprovalType::class)),
                    'duration' => 0,
                    'remarks' => fake()->text(),
                ],
            ],
        ];

        $response = $this->withToken($this->authTokenAdmin)->putJson($this->baseUri.'/'.$locatorSlip->id, $createNewLogs);
        $response->assertStatus(200);

        $response = $response->decodeResponseJson()['data'];
        $this->assertTrue(array_intersect_assoc($createNewLogs['locator_slip_logger'][0], $response['locator_slip_logger'][0]) === $createNewLogs['locator_slip_logger'][0]); // Assert that the logs are created
    }

    public function test_it_can_update_locator_slip_logs(): void
    {
        $individual = IndividualBasicDetail::factory()->create();
        $employee = Employee::whereBelongsTo($individual)->firstOrFail();

        $date = now()->toDateString();
        $locatorSlip = LocatorSlip::factory()->create(['employee_id' => $employee->id, 'date' => $date]);
        $locatorSlipLogs = LocatorSlipLogger::factory()->create(['locator_slip_id' => $locatorSlip->id, 'date' => $date]);

        $updateLogs = [
            'locator_slip_logger' => [
                [
                    'id' => $locatorSlipLogs->id,
                    'date' => $date,
                    'time_out' => null,
                    'time_in' => null,
                    'destination' => fake()->text(),
                    'purpose' => fake()->text(),
                    'approved_for' => fake()->randomElement(ConversionHelper::enumToArray(ApprovalType::class)),
                    'duration' => 0,
                    'remarks' => fake()->text(),
                ],
            ],
        ];

        $response = $this->withToken($this->authTokenAdmin)->putJson($this->baseUri.'/'.$locatorSlip->id, $updateLogs);
        $response->assertStatus(200);

        $response = $response->decodeResponseJson()['data'];
        $this->assertTrue(array_intersect_assoc($updateLogs['locator_slip_logger'][0], $response['locator_slip_logger'][0]) === $updateLogs['locator_slip_logger'][0]); // Assert that the log is updated

    }
}
