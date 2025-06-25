<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\ComprehensiveRecords\Employee;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\DailyTimeRecords\QrCode;
use App\Models\User;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use Crypt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrCodeFeatureTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUri = self::BASE_API_URI.'/employees';

    private User $user_ppms;

    private User $user_pas;

    private string $authTokenPPMS;

    private string $authTokenPAS;

    private PersistentAuthTokenManager $tokenManager;

    public function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');

        $user_ppms = $this->produceUsers();
        $user_pas = $this->produceUsers();
        $roles = [RoleEnum::HR_PPMS_ADMIN, RoleEnum::HR_PAS_ADMIN];
        $user_ppms->syncRoles($roles[0]);
        $user_pas->syncRoles($roles[1]);
        $this->user_ppms = $user_ppms; // save ppms admin user
        $this->user_pas = $user_pas; // save standard user

        $this->tokenManager = resolve(PersistentAuthTokenManager::class);
        $authTokenExpirationPPMS = now()->addMinutes(config('sanctum.expiration'));
        $this->authTokenPPMS = $this->tokenManager->generateToken($user_ppms, $authTokenExpirationPPMS, 'mock_token');

        $authTokenExpirationPAS = now()->addMinutes(config('sanctum.expiration'));
        $this->authTokenPAS = $this->tokenManager->generateToken($user_pas, $authTokenExpirationPAS, 'mock_token');
    }

    public function test_it_can_generate_qr_code(): void
    {
        $individual = IndividualBasicDetail::factory()->create();
        $employee = Employee::whereBelongsTo($individual)->firstOrFail();
        $response = $this->withToken($this->authTokenPAS)->postJson($this->baseUri."/$employee->id/qr-codes");
        $response->assertStatus(201);
    }

    public function test_it_can_show_qr_code_of_employee(): void
    {
        $qrCode = QrCode::factory()->create();
        $employee = Employee::find($qrCode->employee_id);

        $response = $this->withToken($this->authTokenPAS)->getJson($this->baseUri."/$employee->id/qr-codes");
        $response->assertStatus(200);

        $this->assertEquals($qrCode->qr_code_value, $response['data']['qr_code_value']);
    }

    public function test_it_can_update_status_of_qr_code(): void
    {
        $qrCode = QrCode::factory()->create();
        $employee = Employee::find($qrCode->employee_id);

        $updatedData = [
            'is_active' => false,
        ];

        $response = $this->withToken($this->authTokenPAS)->patchJson($this->baseUri."/$employee->id/qr-codes", $updatedData);
        $response->assertStatus(200);

        $this->assertEquals(false, $response['data']['is_active']);
    }

    public function test_it_can_verify_qr_code(): void
    {
        $qrCode = QrCode::factory()->create();
        $employee = Employee::find($qrCode->employee_id);

        $data = [
            'scanned_qr' => $qrCode->qr_code_value,
        ];

        $response = $this->withToken($this->authTokenPAS)->postJson($this->baseUri.'/verify-qr', $data);
        $response->assertStatus(200);

        $this->assertEquals($employee->id, $response['data']['id']);
    }

    public function test_it_can_verify_inactive_qr_code(): void
    {
        $qrCode = QrCode::factory()->create();
        $employee = Employee::find($qrCode->employee_id);

        $updatedData = [
            'is_active' => false,
        ];

        // Set is_active to false
        $response = $this->withToken($this->authTokenPAS)->patchJson($this->baseUri."/$employee->id/qr-codes", $updatedData);
        $response->assertStatus(200);

        $data = [
            'scanned_qr' => $qrCode->qr_code_value,
        ];
        $response = $this->withToken($this->authTokenPAS)->postJson($this->baseUri.'/verify-qr', $data);
        $response->assertStatus(401); //Should throw UNAUTHORIZED_ERROR

    }

    public function test_it_can_verify_invalid_qr_code(): void
    {
        $qrCode = QrCode::factory()->create();
        $employee = Employee::find($qrCode->employee_id);

        $data = [
            'scanned_qr' => 'invalidQr',
        ];
        $response = $this->withToken($this->authTokenPAS)->postJson($this->baseUri.'/verify-qr', $data);
        $response->assertStatus(400); //Should throw BAD_REQUEST_ERROR

    }

    public function test_it_can_verify_when_qr_does_not_belong_to_any_employee(): void
    {
        $individual = IndividualBasicDetail::factory()->create(); // Generate employee but do not create QR code.
        $employee = Employee::whereBelongsTo($individual)->firstOrFail();

        $data = [
            'scanned_qr' => Crypt::encrypt($employee->id_number),
        ];
        $response = $this->withToken($this->authTokenPAS)->postJson($this->baseUri.'/verify-qr', $data);
        $response->assertStatus(404); //Should throw RESOURCE_NOT_FOUND_ERROR

    }

    public function test_ppms_cannot_generate_qr(): void
    {
        $individual = IndividualBasicDetail::factory()->create();
        $employee = Employee::whereBelongsTo($individual)->firstOrFail();
        $response = $this->withToken($this->authTokenPPMS)->postJson($this->baseUri."/$employee->id/qr-codes");
        $response->assertStatus(403); // Should throw UNAUTHORIZED_ERROR
    }
}
