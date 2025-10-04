<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\AccomplishmentReport;
use App\Models\ARRows;
use App\Models\User;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class AccomplishmentReportFeatureTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUri = self::BASE_API_URI.'/accomplishment-reports';

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

    public static function validCreateARInputs(): array
    {
        $requiredFieldsOnly = [
            'period' => '1-15 January 2025',
            'rows' => [
                [
                    'week_num' => 'Week 1',
                ],
            ],
        ];

        $allFields = [
            'period' => '1-15 January 2025',
            'rows' => [
                [
                    'week_num' => 'Week 1',
                    'dates_in_week' => '(1-3 January 2025)',
                    'specific_activity' => 'sample activity',
                    'highlights' => 'sample highlights',
                ],
                [
                    'week_num' => 'Week 2',
                    'dates_in_week' => '(6-10 January 2025)',
                    'specific_activity' => 'sample activity 2',
                    'highlights' => 'sample highlights 2',
                ],
            ],
            'supervisor_notes' => 'sample notes',
        ];

        $missingRequiredFields = Arr::except(
            $allFields,
            ['period', 'rows']
        );

        return [
            [$requiredFieldsOnly, 201],
            [$allFields, 201],
            [$missingRequiredFields, 422],
        ];
    }

    /**
     * @dataProvider validCreateARInputs
     *
     * @note we can't use Eloquent nor faker in data providers
     *
     * @throws Throwable
     */
    public function test_it_can_create_an_accomplishment_report($input, $statusCode): void
    {

        $response = $this->withToken($this->authToken)->postJson($this->baseUri, $input);
        $response->assertStatus($statusCode);

        if ($statusCode !== 422) {
            $response = $response->decodeResponseJson()['data'];
            $createdAR = AccomplishmentReport::find($response['id']);
            $createdRows = ARRows::where('accomplishment_report_id', '=', $response['id'])->get();

            // check if record exists
            $this->assertNotEmpty($createdAR);
            $this->assertNotEmpty($createdRows);
        }
    }

    public function test_it_cannot_read_other_users_accomplishment_report(): void
    {
        $othersAR = AccomplishmentReport::factory()->hasProfile()->isDraft()->create(); // this generates an AR with a different user

        $responseOthers = $this->withToken($this->authToken)->getJson("$this->baseUri/$othersAR->id");
        $responseOthers->assertStatus(403); // should result in unauthorize

    }

    public function test_it_can_read_current_user_own_accomplishment_report(): void
    {
        $ownAR = AccomplishmentReport::factory()->hasProfile($this->user)->isDraft()->create(); // this generates an AR with the current user
        $ownAR = AccomplishmentReport::find($ownAR->id);

        $responseOwn = $this->withToken($this->authToken)->getJson("$this->baseUri/$ownAR->id");
        $responseOwn->assertStatus(200);
    }

    public function test_it_can_read_with_filters(): void
    {
        $isDraft = AccomplishmentReport::factory(3)->hasProfile($this->user)->isDraft()->create(); // creates an AR with the current user, with draft status
        $isDone = AccomplishmentReport::factory(2)->hasProfile($this->user)->isDone()->create(); // creates an AR with the current user, with done status

        $filter = 'status=done';
        $response = $this->withToken($this->authToken)->getJson("$this->baseUri?$filter");
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');

        $filter = 'status=draft';
        $response = $this->withToken($this->authToken)->getJson("$this->baseUri?$filter");
        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_it_can_read_all_without_filters(): void
    {
        $isDraft = AccomplishmentReport::factory(3)->hasProfile($this->user)->isDraft()->create(); // creates an AR with the current user, with draft status
        $isDone = AccomplishmentReport::factory(2)->hasProfile($this->user)->isDone()->create(); // creates an AR with the current user, with done status

        $response = $this->withToken($this->authToken)->getJson("$this->baseUri");
        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data'); // should fetch all regardless of status
    }

    public function test_it_can_only_read_own_record(): void
    {
        $othersAR = AccomplishmentReport::factory(3)->hasProfile()->isDraft()->create(); // creates an AR with other user
        $ownAR = AccomplishmentReport::factory(2)->hasProfile($this->user)->isDraft()->create(); // creates an AR with current user

        $response = $this->withToken($this->authToken)->getJson("$this->baseUri");
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_it_cannot_update_others_accomplishment_report(): void
    {
        $othersAR = AccomplishmentReport::factory(3)->hasProfile()->isDraft()->create(); // creates an AR with other user
        $firstAR = $othersAR->first();
        $firstRow = ARRows::where('accomplishment_report_id', '=', $firstAR->id)->first();

        $updatedData = [
            'period' => '1-15 January 2025',
            'rows' => [
                [
                    'id' => $firstRow->id,
                    'week_num' => 'Week 1',
                    'dates_in_week' => '(1-3 January 2025)',
                    'specific_activity' => 'sample activity',
                    'highlights' => 'sample highlights',
                ],
            ],
            'status' => 'done',
        ];

        $response = $this->withToken($this->authToken)->putJson("$this->baseUri/$firstAR->id", $updatedData);
        $response->assertStatus(403); // should result in unauthorize
    }

    public function test_it_can_update_own_accomplishment_report(): void
    {
        $ownAR = AccomplishmentReport::factory(3)->hasProfile($this->user)->isDraft()->create(); // creates an AR with current user, and is draft
        $firstAR = $ownAR->first();
        $firstRow = ARRows::where('accomplishment_report_id', '=', $firstAR->id)->first();

        $updatedData = [
            'period' => '1-15 January 2025',
            'rows' => [
                [
                    'id' => $firstRow->id,
                    'week_num' => $firstRow->week_num,
                    'dates_in_week' => '(1-3 January 2025)',
                    'specific_activity' => 'sample activity',
                    'highlights' => 'sample highlights',
                ],
            ],
            'status' => 'done',
        ]; // update status to done

        $response = $this->withToken($this->authToken)->putJson("$this->baseUri/$firstAR->id", $updatedData);
        $response->assertStatus(200);

        $response = $response->decodeResponseJson()['data'];

        $this->assertEquals($response['status'], $updatedData['status']); // status should now be done

    }

    public function test_it_can_update_existing_rows(): void
    {
        $testARs = AccomplishmentReport::factory(2)->hasProfile($this->user)->isDraft()->create(); // creates 2 AR with 3 rows each
        $firstAR = $testARs->first();
        $firstARRowsCollection = ARRows::where('accomplishment_report_id', '=', $firstAR->id)->get();
        $rowsArray = $firstARRowsCollection->pluck('id')->toArray();

        $newRowData = [
            'period' => '1-15 January 2025',
            'rows' => [
                [
                    'id' => $rowsArray[0], // update the first row on the AR
                    'week_num' => 'Week 1',
                    'dates_in_week' => '(1-3 January 2025)',
                    'specific_activity' => 'sample activity',
                    'highlights' => 'sample highlights',
                ],
                [
                    'id' => $rowsArray[1], // update the second row on the AR
                    'week_num' => 'Week 2',
                    'dates_in_week' => '(6-10 January 2025)',
                    'specific_activity' => 'sample activity 2',
                    'highlights' => 'sample highlights 2',
                ],
            ],
        ];

        $response = $this->withToken($this->authToken)->putJson("$this->baseUri/$firstAR->id", $newRowData);
        $response->assertStatus(200);

        $this->assertDatabaseHas('a_r_rows', $newRowData['rows'][0]); // assert that the newly updated row exists in the database
        $this->assertDatabaseHas('a_r_rows', $newRowData['rows'][1]); // assert that the newly updated row exists in the database
    }

    public function test_it_can_create_new_rows(): void
    {
        $testARs = AccomplishmentReport::factory(2)->hasProfile($this->user)->isDraft()->create(); // creates 2 AR with 3 rows each
        $firstAR = $testARs->first();

        $newRowData = [
            'period' => '1-15 January 2025',
            'rows' => [
                [
                    'week_num' => 'Week 1',
                ],
            ],
        ];

        $response = $this->withToken($this->authToken)->putJson("$this->baseUri/$firstAR->id", $newRowData);
        $response->assertStatus(200);

        $decodedResponse = $response->decodeResponseJson();

        $createdRowId = $decodedResponse['data']['rows'][3]['id']; // get the fourth row since there are 3 existing before the new one is created

        $this->assertDatabaseHas('a_r_rows', ['id' => $createdRowId]); // assert that the newly created row exists in the database
    }

    public function test_it_can_update_existing_rows_and_create_new_rows(): void
    {
        $testARs = AccomplishmentReport::factory(2)->hasProfile($this->user)->isDraft()->create(); // creates 2 AR with 3 rows each
        $firstAR = $testARs->first();
        $firstARRowsCollection = ARRows::where('accomplishment_report_id', '=', $firstAR->id)->get();
        $rowsArray = $firstARRowsCollection->pluck('id')->toArray();

        $newRowData = [
            'period' => '1-15 January 2025',
            'rows' => [
                [
                    'id' => $rowsArray[0], // update the first row on the AR
                    'week_num' => 'Week 1',
                    'dates_in_week' => '(1-3 January 2025)',
                    'specific_activity' => 'sample activity',
                    'highlights' => 'sample highlights',
                ],
                [
                    'id' => $rowsArray[1], // update the second row on the AR
                    'week_num' => 'Week 2',
                    'dates_in_week' => '(6-10 January 2025)',
                    'specific_activity' => 'sample activity 2',
                    'highlights' => 'sample highlights 2',
                ],
                [
                    'week_num' => 'Week 3',
                ],
            ],
        ];

        $response = $this->withToken($this->authToken)->putJson("$this->baseUri/$firstAR->id", $newRowData);
        $response->assertStatus(200);

        $this->assertDatabaseHas('a_r_rows', $newRowData['rows'][0]); // assert that the newly updated row exists in the database
        $this->assertDatabaseHas('a_r_rows', $newRowData['rows'][1]); // assert that the newly updated row exists in the database

        $decodedResponse = $response->decodeResponseJson();

        $createdRowId = $decodedResponse['data']['rows'][3]['id']; // get the fourth row since there are 3 existing before the new one is created
        $this->assertDatabaseHas('a_r_rows', ['id' => $createdRowId]); // assert that the newly created row exists in the database
    }

    public function test_it_cannot_update_rows_that_does_not_belong_to_the_accomplishment_report(): void
    {
        $testARs = AccomplishmentReport::factory(2)->hasProfile($this->user)->isDraft()->create(); // creates 2 AR with 3 rows each
        $firstAR = $testARs->first();
        $secondAR = $testARs->get(1);
        $secondARRowsCollection = ARRows::where('accomplishment_report_id', '=', $secondAR->id)->get(); // get the rows of the second AR
        $rowsArray = $secondARRowsCollection->pluck('id')->toArray(); // save the ids as array

        $newRowData = [
            'period' => '1-15 January 2025',
            'rows' => [
                [
                    'id' => $rowsArray[0], // attempt to update a row that does not belong to the first AR
                    'week_num' => 'Week 1',
                    'dates_in_week' => '(1-3 January 2025)',
                    'specific_activity' => 'sample activity',
                    'highlights' => 'sample highlights',
                ],
            ],
            'supervisor_notes' => 'sample notes',
        ];

        $response = $this->withToken($this->authToken)->putJson("$this->baseUri/$firstAR->id", $newRowData); // pass the id of the first AR but the row is in the second AR
        $response->assertStatus(422); // should return a response status code of 422
        $response->assertJsonFragment([
            'message' => 'A validation error has occurred',
            'error_code' => 'VALIDATION_ERROR',
            'errors' => [
                [
                    'field' => 'rows.0.id',
                    'messages' => [
                        'The rows.0.id does not belong to the accomplishment report.',
                    ],
                ],
            ],
        ]);
    }

    public function test_it_can_generate_docx(): void
    {
        $ownAR = AccomplishmentReport::factory()->hasProfile($this->user)->isDraft()->create(); // creates an AR with current user, and is draft

        $response = $this->withToken($this->authToken)->get("$this->baseUri/$ownAR->id/generate");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }
}
