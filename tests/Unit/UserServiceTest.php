<?php

namespace Tests\Unit;

use App\Enums\PaginationType;
use App\Interfaces\HttpResources\UserServiceInterface;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\HttpResources\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Tests\TestCase;
use Throwable;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    private UserServiceInterface $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->userService = new UserService(new User());
    }

    /** @throws Throwable */
    public function test_it_can_create_a_user()
    {
        $this->userService->create($this->getUserDetails());
        $this->assertDatabaseCount('users', 1);
    }

    /** @throws Throwable */
    public function test_it_can_update_a_user()
    {
        $user = User::factory()->has(UserProfile::factory())->create();
        $edited = ['first_name' => fake()->firstName, 'last_name' => fake()->lastName];
        $editedUser = $this->userService->update($user->id, $edited);

        $this->assertEquals($edited['first_name'], $editedUser->userProfile->first_name);
        $this->assertEquals($edited['last_name'], $editedUser->userProfile->last_name);
    }

    public function test_it_can_fetch_all_users()
    {
        $count = 10;
        User::factory()->has(UserProfile::factory())->count($count)->create();

        $users = $this->userService->all();
        $this->assertCount($count, $users);
    }

    public function test_it_can_fetch_all_users_with_pagination()
    {
        $count = 10;
        User::factory()->has(UserProfile::factory())->count($count)->create();

        $request = new Request();
        $limit = 5;
        $request->replace(['limit' => $limit]);
        app()->instance('request', $request);

        $users = $this->userService->all(PaginationType::LENGTH_AWARE);

        $this->assertEquals($count, $users->total());
        $this->assertEquals($limit, count($users->items()));
    }

    /**
     * Generate user info
     */
    private function getUserDetails(): array
    {
        return [
            'email' => fake()->unique()->safeEmail,
            'first_name' => fake()->firstName,
            'last_name' => fake()->lastName,
            'password' => 'Sample123_123',
            'password_confirmation' => 'Sample123_123',
            'active' => fake()->boolean,
            'email_verified' => fake()->boolean,
            'middle_name' => $this->faker->lastName,
            'mobile_number' => '+63906'.fake()->unique()->randomNumber(7),
            'telephone_number' => '+6327'.fake()->randomNumber(7),
            'sex' => fake()->randomElement(['male', 'female']),
            'birthday' => '1997-01-05',
            'address_line_1' => $this->faker->buildingNumber,
            'address_line_2' => $this->faker->streetName,
            'address_line_3' => $this->faker->streetAddress,
            'district' => 'District 1',
            'city' => $this->faker->city,
            'province' => 'Province 1',
            'postal_code' => $this->faker->postcode,
            'country' => $this->faker->country,
            'profile_picture_path' => $this->faker->filePath,
        ];
    }
}
