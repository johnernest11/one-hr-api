<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalaryGradeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $authToken;

    private PersistentAuthTokenManager $tokenManager;

    protected function setUp(): void
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

    private string $baseUri = self::BASE_API_URI.'/libraries/salary-grades';

    /**
     * Test filters
     *
     * @return array<array<int|string|null>>
     */
    public static function validFilters(): array
    {
        return [
            [null, null],
            ['nbc_no', 591],
            ['effective-date', '2025'],
            ['tranche', 4],
            ['sg', 18],
            ['step', 1],
            ['active', 1],
        ];
    }

    /**
     * @dataProvider validFilters
     *
     * @note we can't use Eloquent nor faker in data providers
     */
    public function test_it_can_fetch_salary_grades($filter_name, $filter_value): void
    {
        if (! $filter_name && ! $filter_value) {
            $response = $this->withToken($this->authToken)->getJson($this->baseUri);
            $response->assertStatus(200);

            return;
        }
        $response = $this->withToken($this->authToken)->getJson($this->baseUri."?$filter_name=$filter_value");
        $response->assertStatus(200);
    }

    public function test_it_can_search_salary_grades(): void
    {

        $query = '18';
        $response = $this->withToken($this->authToken)->getJson($this->baseUri."/search?query=$query");
        $response = $response->decodeResponseJson();

        $this->assertEquals(16, $response['pagination']['total']);
    }
}
