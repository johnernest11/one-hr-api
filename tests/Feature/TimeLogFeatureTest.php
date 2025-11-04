<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\DailyTimeRecords\QrCode;
use App\Models\User;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TimeLogFeatureTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUri = self::BASE_API_URI.'/employees';

    private User $user_ppms;

    private User $standard_user;

    private User $user_pas;

    private string $authTokenPPMS;

    private string $authTokenPAS;

    private string $authTokenStandard;

    private PersistentAuthTokenManager $tokenManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');

        $user_ppms = $this->produceUsers();
        $user_pas = $this->produceUsers();
        $standard_user = $this->produceUsers();
        $roles = [RoleEnum::HR_PPMS_ADMIN, RoleEnum::HR_PAS_ADMIN, RoleEnum::STANDARD_USER];
        $user_ppms->syncRoles($roles[0]);
        $user_pas->syncRoles($roles[1]);
        $standard_user->syncRoles($roles[2]);
        $this->user_ppms = $user_ppms;
        $this->user_pas = $user_pas;
        $this->standard_user = $standard_user;

        $this->tokenManager = resolve(PersistentAuthTokenManager::class);
        $authTokenExpirationPPMS = now()->addMinutes(config('sanctum.expiration'));
        $this->authTokenPPMS = $this->tokenManager->generateToken($user_ppms, $authTokenExpirationPPMS, 'mock_token');

        $authTokenExpirationPAS = now()->addMinutes(config('sanctum.expiration'));
        $this->authTokenPAS = $this->tokenManager->generateToken($user_pas, $authTokenExpirationPAS, 'mock_token');

        $authTokenExpirationStandard = now()->addMinutes(config('sanctum.expiration'));
        $this->authTokenStandard = $this->tokenManager->generateToken($standard_user, $authTokenExpirationStandard, 'mock_token');
    }

    public function test_it_can_log_time(): void
    {
        $qrCode = QrCode::factory()->create();

        $data = [
            'scanned_qr' => $qrCode->qr_code_value,
        ];

        $response = $this->withToken($this->authTokenPAS)->postJson($this->baseUri.'/log-time', $data);
        $response->assertStatus(200);

        $currentTime = Carbon::now()->format('H:i');
        $carbonResponse = Carbon::parse($response['data']['scanned_time'])->format('H:i');

        $this->assertEquals($currentTime, $carbonResponse);

        $this->assertDatabaseCount('time_logs', 1);
    }

    public function test_duplicate_scans(): void
    {
        $qrCode = QrCode::factory()->create();

        $data = [
            'scanned_qr' => $qrCode->qr_code_value,
        ];

        $response = $this->withToken($this->authTokenPAS)->postJson($this->baseUri.'/log-time', $data);
        $response->assertStatus(200);

        $currentTime = Carbon::now()->format('H:i');
        $carbonResponse = Carbon::parse($response['data']['scanned_time'])->format('H:i');
        $this->assertEquals($currentTime, $carbonResponse);

        $this->assertDatabaseCount('time_logs', 1);

        $response = $this->withToken($this->authTokenPAS)->postJson($this->baseUri.'/log-time', $data);
        $response->assertStatus(422);

        $this->assertDatabaseCount('time_logs', 1);
    }

    public function test_it_can_save_capture_image(): void
    {
        $qrCode = QrCode::factory()->create();
        $file = UploadedFile::fake()->image('fake_image.jpg', 500, 500);

        $data = [
            'scanned_qr' => $qrCode->qr_code_value,
            'captured_image' => $file,
        ];

        $response = $this
            ->withToken($this->authTokenPAS)
            ->postJson($this->baseUri.'/log-time', $data);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'data',
            'captured_image_url',
        ]);

        $responseData = $response->json();

        $this->assertArrayHasKey('captured_image_url', $responseData);
        $this->assertNotEmpty($responseData['captured_image_url'], 'captured_image_url should not be empty or null');

        Storage::disk('s3')->deleteDirectory('images/time-logs');
    }
}
