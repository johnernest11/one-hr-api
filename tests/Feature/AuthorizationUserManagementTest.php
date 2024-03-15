<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthorizationUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $baseUri = self::BASE_API_URI.'/users';

    private string $authToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');

        /** @var User $user */
        $this->user = $this->produceUsers();
        $this->user->syncRoles(RoleEnum::ADMIN);

        $authSanctumService = resolve(PersistentAuthTokenManager::class);
        $authTokenExpiration = now()->addMinutes(config('sanctum.expiration'));
        $this->authToken = $authSanctumService->generateToken($this->user, $authTokenExpiration, 'mock_token');
    }

    public function test_admins_can_create_a_user(): void
    {
        $response = $this->withToken($this->authToken)->post($this->baseUri, $this->getRequiredUserInputSample());
        $response->assertStatus(201);

        // Change user permission and refresh the user in the auth guard
        $this->user->syncRoles(RoleEnum::STANDARD_USER);
        auth('token')->setUser($this->user);

        $response = $this->withToken($this->authToken)->postJson($this->baseUri, $this->getRequiredUserInputSample());
        $response->assertStatus(403);
    }

    public function test_admins_can_update_a_user(): void
    {
        $user = $this->produceUsers();
        $response = $this->withToken($this->authToken)
            ->patchJson("$this->baseUri/$user->id", $this->getRequiredUserInputSample());

        $response->assertStatus(200);
    }

    public function test_non_admins_cannot_update_a_user(): void
    {
        $user = $this->produceUsers();
        $this->user->syncRoles(RoleEnum::STANDARD_USER);
        $response = $this->withToken($this->authToken)->patchJson("$this->baseUri/$user->id", $this->getRequiredUserInputSample());
        $response->assertStatus(403);
    }

    public function test_admins_can_get_all_users(): void
    {
        $response = $this->withToken($this->authToken)->getJson("$this->baseUri");
        $response->assertStatus(200);
    }

    public function test_non_admins_can_not_get_all_users(): void
    {
        $this->user->syncRoles(RoleEnum::STANDARD_USER);
        $response = $this->withToken($this->authToken)->getJson("$this->baseUri");
        $response->assertStatus(403);
    }

    public function test_admins_can_read_a_user(): void
    {
        $user = $this->produceUsers();
        $response = $this->withToken($this->authToken)->getJson("$this->baseUri/$user->id");
        $response->assertStatus(200);
    }

    public function test_non_admins_cannot_read_a_user(): void
    {
        $user = $this->produceUsers();
        $this->user->syncRoles(RoleEnum::STANDARD_USER);
        $response = $this->withToken($this->authToken)->getJson("$this->baseUri/$user->id");
        $response->assertStatus(403);
    }

    public function test_admins_can_delete_users(): void
    {
        $user = $this->produceUsers();
        $response = $this->withToken($this->authToken)->delete("$this->baseUri/$user->id");
        $response->assertStatus(204);
    }

    public function test_non_admins_cannot_delete_a_user(): void
    {
        $user = $this->produceUsers();
        $this->user->syncRoles(RoleEnum::STANDARD_USER);

        $response = $this->withToken($this->authToken)->delete("$this->baseUri/$user->id");
        $response->assertStatus(403);
    }

    public function test_only_admins_can_upload_a_profile_picture_of_a_user(): void
    {
        $user = $this->produceUsers();
        $file = UploadedFile::fake()->image('fake_image.jpg', 500, 500);

        $response = $this->withToken($this->authToken)->postJson("$this->baseUri/$user->id/profile-picture", ['photo' => $file]);
        $response->assertStatus(200);

        // clean the bucket
        Storage::disk('s3')->deleteDirectory('images/');
    }

    public function test_non_admins_can_not_upload_a_profile_picture_of_a_user(): void
    {
        $user = $this->produceUsers();
        $file = UploadedFile::fake()->image('fake_image.jpg', 500, 500);
        $this->user->syncRoles(RoleEnum::STANDARD_USER);

        $response = $this->withToken($this->authToken)->postJson("$this->baseUri/$user->id/profile-picture", ['photo' => $file]);
        $response->assertStatus(403);

        // clean the bucket
        Storage::disk('s3')->deleteDirectory('images/');
    }

    public function test_super_users_cannot_be_deleted(): void
    {
        $user = $this->produceUsers();
        $user->syncRoles(RoleEnum::SUPER_USER);

        $response = $this->withToken($this->authToken)->delete("$this->baseUri/$user->id");
        $response->assertStatus(403);
    }

    public function test_super_users_cannot_be_updated(): void
    {
        $user = $this->produceUsers();
        $user->syncRoles(RoleEnum::SUPER_USER);

        $response = $this->withToken($this->authToken)->patchJson("$this->baseUri/$user->id", ['first_name' => 'Something']);
        $response->assertStatus(403);
    }

    public function test_block_unverified_email_address_from_accessing_endpoints(): void
    {
        /** @var User $user */
        $user = $this->produceUsers();
        $user->syncRoles(RoleEnum::ADMIN);
        $user->update(['email_verified_at' => null]);
        $token = resolve(PersistentAuthTokenManager::class)->generateToken($user, now()->addMinute());

        $response = $this->withToken($token)->getJson("$this->baseUri");
        $response->assertStatus(403);
    }
}
