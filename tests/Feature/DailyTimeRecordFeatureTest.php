<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\ComprehensiveRecords\Employee;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\DailyTimeRecords\DailyTimeRecord;
use App\Models\DailyTimeRecords\TimeLog;
use App\Models\Libraries\SectionOrUnit;
use App\Models\User;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyTimeRecordFeatureTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUri = self::BASE_API_URI.'/employees/daily-time-records';

    private string $uriWithId = self::BASE_API_URI.'/employees';

    private User $user_ppms;

    private User $user_pas;

    private User $standard_user;

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
        $this->user_ppms = $user_ppms; // save ppms admin user
        $this->user_pas = $user_pas; // save pas user
        $this->standard_user = $standard_user; // save standard user

        $this->tokenManager = resolve(PersistentAuthTokenManager::class);
        $authTokenExpirationPPMS = now()->addMinutes(config('sanctum.expiration'));
        $this->authTokenPPMS = $this->tokenManager->generateToken($user_ppms, $authTokenExpirationPPMS, 'mock_token');

        $authTokenExpirationPAS = now()->addMinutes(config('sanctum.expiration'));
        $this->authTokenPAS = $this->tokenManager->generateToken($user_pas, $authTokenExpirationPAS, 'mock_token');

        $authTokenExpirationStandard = now()->addMinutes(config('sanctum.expiration'));
        $this->authTokenStandard = $this->tokenManager->generateToken($standard_user, $authTokenExpirationStandard, 'mock_token');

    }

    public function test_it_can_view_all_time_logs(): void
    {
        $timelogs = TimeLog::factory(5)->alternatingIsIn()->create();

        $response = $this->withToken($this->authTokenPAS)->getJson($this->baseUri.'/time-logs');
        $response->assertStatus(200);

        $this->assertEquals(5, $response['pagination']['total']);
    }

    private function createEmployeeWithDivision(int $divisionId): Employee
    {
        $sectionId = SectionOrUnit::where('division_id', $divisionId)->first()->id;

        $individual = IndividualBasicDetail::factory()
            ->afterCreating(function (IndividualBasicDetail $individual) use ($divisionId, $sectionId) {
                $individual->employee->update([
                    'division_id' => $divisionId,
                    'section_or_unit_id' => $sectionId,
                ]);
            })
            ->create();

        return $individual->employee;
    }

    public function test_it_can_count_warm_bodies(): void
    {
        $currentDate = now()->toDateString();
        $selectedDivId = 2;
        $selectedSecId = SectionOrUnit::where('division_id', $selectedDivId)->first()->id;

        // Generate Sample Employee
        $employee1 = $this->createEmployeeWithDivision($selectedDivId);
        $employee2 = $this->createEmployeeWithDivision($selectedDivId);

        // Generate Time Log (2 employees are in the office)
        $dailyTimeRecord1 = DailyTimeRecord::factory()->create([
            'employee_id' => $employee1->id,
            'date' => $currentDate,
        ]);

        TimeLog::factory()
            ->forDailyTimeRecord($dailyTimeRecord1)
            ->alternatingIsIn()
            ->create();

        $dailyTimeRecord2 = DailyTimeRecord::factory()->create([
            'employee_id' => $employee2->id,
            'date' => $currentDate,
        ]);

        TimeLog::factory()
            ->forDailyTimeRecord($dailyTimeRecord2)
            ->alternatingIsIn()
            ->create();

        // Call API
        $response = $this->withToken($this->authTokenPAS)->getJson($this->baseUri.'/warm-bodies/count');
        $response->assertStatus(200);

        $response = $response->decodeResponseJson()['data'];

        // Assert Employee Count
        $this->assertSame(Employee::count(), $response['total_employees']);

        // Assert that division summary is correct (Both employees are in the office.)
        $div = collect($response['per_division'])->firstWhere('division_id', $selectedDivId);
        $this->assertSame(2, $div['in_office']);
        $this->assertSame(0, $div['out_of_office']);
        $this->assertSame(2, $div['total_employees']);

        // Assert that section summary is correct (Both employees are in the office.)
        $sec = collect($response['per_section'])->firstWhere('section_id', $selectedSecId);
        $this->assertSame(2, $sec['in_office']);
        $this->assertSame(0, $sec['out_of_office']);
        $this->assertSame(2, $sec['total_employees']);

        // Generate Time Log (1 employee is now out of the office)
        TimeLog::factory()
            ->forDailyTimeRecord($dailyTimeRecord2)
            ->create([
                'is_in' => 0,
            ]);

        // Call API
        $response = $this->withToken($this->authTokenPAS)->getJson($this->baseUri.'/warm-bodies/count');
        $response->assertStatus(200);

        $response = $response->decodeResponseJson()['data'];

        // Assert Employee Count
        $this->assertSame(Employee::count(), $response['total_employees']);

        // Assert that division summary is correct (One of the employees are now not in the office.)
        $div = collect($response['per_division'])->firstWhere('division_id', $selectedDivId);
        $this->assertSame(1, $div['in_office']);
        $this->assertSame(1, $div['out_of_office']);
        $this->assertSame(2, $div['total_employees']);

        // Assert that section summary is correct (One of the employees are now not in the office.)
        $sec = collect($response['per_section'])->firstWhere('section_id', $selectedSecId);
        $this->assertSame(1, $sec['in_office']);
        $this->assertSame(1, $sec['out_of_office']);
        $this->assertSame(2, $div['total_employees']);
    }

    public function test_it_can_view_all_warm_bodies_today(): void
    {
        $dateToday = Carbon::now()->toDateString();
        $timelogsToday = TimeLog::factory(3)->alternatingIsIn()->setDate($dateToday)->create();
        $timelogsRandom = TimeLog::factory(5)->alternatingIsIn()->create();

        $response = $this->withToken($this->authTokenPAS)->getJson($this->baseUri.'/warm-bodies/today');
        $response->assertStatus(200);

        // Will now expect 2 logs as result. This is because in $timelogsToday, 3 records where created for 3 employees,
        // but they have the following values for is_in: true, false, true; in that order.
        // Therefore, the updated API will now only register 2 warm bodies, or 2 employees currently in, since one has timed out.
        $this->assertEquals(2, $response['pagination']['total']);
    }

    public function test_it_can_view_dtr_per_month(): void
    {
        $individual = IndividualBasicDetail::factory()->create();
        $employee = Employee::whereBelongsTo($individual)->firstOrFail();

        $startDate = Carbon::create(2025, 6, 1); // Sample Start Date
        $numberOfDTRs = 3;
        for ($i = 0; $i < $numberOfDTRs; $i++) {
            $currentDate = $startDate->copy()->addDays($i)->toDateString();

            $dailyTimeRecord = DailyTimeRecord::factory()->create([
                'employee_id' => $employee->id,
                'date' => $currentDate,
            ]);

            $timeLogs = TimeLog::factory()
                ->forDailyTimeRecord($dailyTimeRecord) // link created DTR
                ->alternatingIsIn()
                ->count(2)
                ->create([
                    'scanned_time' => Carbon::parse($currentDate.' 08:00:00')->toTimeString(), // First log (IN)
                ])
                ->each(function (TimeLog $log, int $key) use ($currentDate) {
                    // Manually adjust the second log's time to be later
                    if ($key === 1) { // This is the second log created (index 1)
                        $log->scanned_time = Carbon::parse($currentDate.' 17:00:00')->toTimeString();
                        $log->save(); // Save the adjusted time
                    }
                });
        }

        $month = '2025-06';

        $response = $this->withToken($this->authTokenPAS)->getJson($this->uriWithId.'/'.$employee->id.'/daily-time-records/view-dtr?month='.$month);
        $response->assertStatus(200);

        $this->assertEquals(3, $response['pagination']['total']);
    }

    public function test_it_can_filter_dtr_per_date_range(): void
    {
        $individual = IndividualBasicDetail::factory()->create();
        $employee = Employee::whereBelongsTo($individual)->firstOrFail();

        $startDate = Carbon::create(2025, 6, 1); // Sample Start Date
        $numberOfDTRs = 3;
        for ($i = 0; $i < $numberOfDTRs; $i++) {
            $currentDate = $startDate->copy()->addDays($i)->toDateString();

            $dailyTimeRecord = DailyTimeRecord::factory()->create([
                'employee_id' => $employee->id,
                'date' => $currentDate,
            ]);

            $timeLogs = TimeLog::factory()
                ->forDailyTimeRecord($dailyTimeRecord) // link created DTR
                ->alternatingIsIn()
                ->count(2)
                ->create([
                    'scanned_time' => Carbon::parse($currentDate.' 08:00:00')->toTimeString(), // First log (IN)
                ])
                ->each(function (TimeLog $log, int $key) use ($currentDate) {
                    // Manually adjust the second log's time to be later
                    if ($key === 1) { // This is the second log created (index 1)
                        $log->scanned_time = Carbon::parse($currentDate.' 17:00:00')->toTimeString();
                        $log->save(); // Save the adjusted time
                    }
                });
        }

        $startDate = '2025-06-01';
        $endDate = '2025-06-15';

        $response = $this->withToken($this->authTokenPAS)->getJson($this->uriWithId.'/'.$employee->id.'/daily-time-records/view-dtr?start_date='.$startDate.'&end_date='.$endDate);
        $response->assertStatus(200);

        $this->assertEquals(3, $response['pagination']['total']);
    }

    public function test_it_can_update_dtr(): void
    {
        $individual = IndividualBasicDetail::factory()->create();
        $employee = Employee::whereBelongsTo($individual)->firstOrFail();

        $date = '2025-06-01';
        $dailyTimeRecord = DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'date' => $date,
        ]);

        $timeLogs = TimeLog::factory()
            ->forDailyTimeRecord($dailyTimeRecord) // link created DTR
            ->alternatingIsIn()
            ->count(2)
            ->create([
                'scanned_time' => Carbon::parse($date.' 08:00:00')->toTimeString(), // First log (IN)
            ])
            ->each(function (TimeLog $log, int $key) use ($date) {
                // Manually adjust the second log's time to be later
                if ($key === 1) { // This is the second log created (index 1)
                    $log->scanned_time = Carbon::parse($date.' 17:00:00')->toTimeString();
                    $log->save(); // Save the adjusted time
                }
            });

        $month = '2025-06';
        $updateRemarks = 'Test Update API';

        $timeLogsIds = $timeLogs->pluck('id');
        $updatedData = [
            'month' => $month,
            'dtr' => [
                [
                    'id' => $dailyTimeRecord->id,
                    'employee_remarks' => $updateRemarks,
                    'time_logs' => [
                        [
                            'id' => $timeLogsIds[0],
                            'is_selected' => false,
                            'scanned_time' => '08:00:00', // Must include this
                        ],
                    ],
                ],
                [
                    'date' => '2025-06-02',
                    'employee_remarks' => 'On Leave',
                ],
            ],
        ];

        $response = $this->withToken($this->authTokenPAS)->putJson($this->uriWithId.'/'.$employee->id.'/daily-time-records', $updatedData);
        $response->assertStatus(200);

        $response = $response->decodeResponseJson()['data'];
        $this->assertEquals($updateRemarks, $response[0]['employee_remarks']); // Assert that the remarks on DTR is updated
        $this->assertEquals(false, $response[0]['time_log'][0]['is_selected']); // Assert that the time log should also be updated
        $this->assertEquals('On Leave', $response[1]['employee_remarks']); // Assert that the new record is also created

    }

    public function test_it_can_search_by_name_in_pas_dashboard(): void
    {
        $individual = IndividualBasicDetail::factory()->create();
        $employee = Employee::whereBelongsTo($individual)->firstOrFail();

        $date = '2025-06-01';
        $dailyTimeRecord = DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'date' => $date,
        ]);

        $timeLogs = TimeLog::factory()
            ->forDailyTimeRecord($dailyTimeRecord) // link created DTR
            ->alternatingIsIn()
            ->count(2)
            ->create([
                'scanned_time' => Carbon::parse($date.' 08:00:00')->toTimeString(), // First log (IN)
            ])
            ->each(function (TimeLog $log, int $key) use ($date) {
                // Manually adjust the second log's time to be later
                if ($key === 1) { // This is the second log created (index 1)
                    $log->scanned_time = Carbon::parse($date.' 17:00:00')->toTimeString();
                    $log->save(); // Save the adjusted time
                }
            });

        $name = $individual->first_name;

        $response = $this->withToken($this->authTokenPAS)->getJson($this->baseUri."/time-logs?query=$name&is_my_profile=0");
        $response->assertStatus(200);

        $this->assertEquals($date, $response['data'][0]['dtr_date']);

    }

    public function test_it_can_search_by_name_in_my_profile(): void
    {
        $pasUserProf = $this->user_pas->userProfile;
        $ownData = IndividualBasicDetail::factory()->withExistingUserProfile($pasUserProf)->create();
        $employee = Employee::whereBelongsTo($ownData)->firstOrFail();

        $date = Carbon::now()->toDateString();
        $dailyTimeRecord = DailyTimeRecord::factory()->create([
            'employee_id' => $employee->id,
            'date' => $date,
        ]);

        $timeLogs = TimeLog::factory()
            ->forDailyTimeRecord($dailyTimeRecord) // link created DTR
            ->alternatingIsIn()
            ->count(1) // Adjusted count so that the last record created is a time in.
            ->create([
                'scanned_time' => Carbon::parse($date.' 08:00:00')->toTimeString(), // First log (IN)
            ]);

        $name = $ownData->first_name;

        $response = $this->withToken($this->authTokenPAS)->getJson($this->baseUri.'/time-logs');
        $response = $this->withToken($this->authTokenPAS)->getJson($this->baseUri."/time-logs/search?query=$name&is_my_profile=1");
        $response->assertStatus(200);

        $response = $response->decodeResponseJson()['data'];
        // Should now be able to find the employee generated via factory. Last record generated is a time in, therefore the employee is currently inside the office.
        $this->assertEquals($name, $response[0]['first_name']);

    }

    public function test_it_can_generate_dtr_pdf(): void
    {
        $individual = IndividualBasicDetail::factory()->create();
        $employee = Employee::whereBelongsTo($individual)->firstOrFail();

        $dates = ['2025-09-01', '2025-09-02', '2025-09-05'];
        foreach ($dates as $date) {
            $dtr = DailyTimeRecord::factory()->create([
                'employee_id' => $employee->id,
                'date' => $date,
                'ut' => 1,
                'ot' => 2,
                'employee_remarks' => 'Test remark',
            ]);
            TimeLog::factory()->count(2)->create([
                'daily_time_record_id' => $dtr->id,
                'is_in' => true,
            ]);
        }

        $startDate = '2025-09-01';
        $endDate = '2025-09-05';
        $sort = 'asc';

        $response = $this->withHeader('Authorization', "Bearer {$this->authTokenStandard}")
            ->get("{$this->uriWithId}/{$employee->id}/daily-time-records/generate-dtr?start_date={$startDate}&end_date={$endDate}&sort={$sort}");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertNotEmpty($response->getContent(), 'Generated DTR PDF content should not be empty');

    }
}
