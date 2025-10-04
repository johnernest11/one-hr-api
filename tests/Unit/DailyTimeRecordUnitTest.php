<?php

namespace Tests\Unit;

use App\Models\ComprehensiveRecords\Employee;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\DailyTimeRecords\DailyTimeRecord;
use App\Models\DailyTimeRecords\TimeLog;
use App\Services\DailyTimeRecords\DailyTimeRecordService;
use App\Services\DailyTimeRecords\TimeLogService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Tests\TestCase;

class DailyTimeRecordUnitTest extends TestCase
{
    use RefreshDatabase;

    private DailyTimeRecordService $dailyTimeRecordService;

    private TimeLogService $timeLogService;

    private Employee $employee;

    private DailyTimeRecord $dailyTimeRecord;

    private IndividualBasicDetail $individual;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->dailyTimeRecordService = new DailyTimeRecordService(new DailyTimeRecord);
        $this->individual = IndividualBasicDetail::factory()->create();
        $this->employee = Employee::whereBelongsTo($this->individual)->firstOrFail();
        $this->timeLogService = new TimeLogService(new TimeLog);
    }

    /**
     * Test if all Time Logs can be viewed via the service
     */
    public function test_can_view_all_time_logs(): void
    {
        $tl = $this->timeLogService->create($this->employee);
        $this->assertDatabaseCount('daily_time_records', 1); // Check that the generated sample record exists in the db

        $paginatedResults = $this->dailyTimeRecordService->all();
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginatedResults);
        $this->assertEquals(1, $paginatedResults->total());
    }

    /**
     * Test if all Daily Time Records can be viewed via the service
     */
    public function test_can_view_dtr_per_period_range(): void
    {
        $tl = $this->timeLogService->create($this->employee);
        $this->assertDatabaseCount('daily_time_records', 1); // Check that the generated sample record exists in the db

        $sampleRequest = [
            'month' => Carbon::now()->format('Y-m'),
        ];

        $paginatedResults = $this->dailyTimeRecordService->viewDtrPerPeriodRange($this->employee, $sampleRequest);
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginatedResults);
        $this->assertEquals(1, $paginatedResults->total());
    }

    /**
     * Test if all warm bodies for the current date can be viewed via the service
     */
    public function test_can_view_warm_bodies_today(): void
    {
        $tl = $this->timeLogService->create($this->employee);
        $this->assertDatabaseCount('daily_time_records', 1); // Check that the generated sample record exists in the db

        $paginatedResults = $this->dailyTimeRecordService->viewWarmBodiesToday();
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginatedResults);
        $this->assertEquals(1, $paginatedResults->total());
    }

    /**
     * Test if DTRs and its time logs can be updated via service
     */
    public function test_can_update(): void
    {
        $tl = $this->timeLogService->create($this->employee);
        $this->assertDatabaseCount('daily_time_records', 1); // Check that the generated sample record exists in the db
        $this->assertEquals(true, $tl->is_selected);
        $dtrId = $tl->dailyTimeRecord->id;

        $sampleRequest = [
            'month' => Carbon::now()->format('Y-m'),
            'dtr' => [
                [
                    'id' => $dtrId,
                    'employee_remarks' => 'Test Update',
                    'time_logs' => [
                        [
                            'id' => $tl->id,
                            'is_selected' => false,
                            'scanned_time' => $tl->scanned_time,
                        ],
                    ],
                ],
            ],
        ];

        $collectionResults = $this->dailyTimeRecordService->update($this->employee, $sampleRequest);
        $this->assertInstanceOf(Collection::class, $collectionResults);
        $this->assertCount(1, $collectionResults);
        $dtr = $collectionResults->first();
        $this->assertEquals('Test Update', $dtr->employee_remarks);
        $updatedTl = TimeLog::find($tl->id);
        $this->assertEquals(false, $updatedTl->is_selected);
    }

    /**
     * Test if time logs can be searched by first/middle/last name via service.
     */
    public function test_can_search_timelogs(): void
    {
        $tl = $this->timeLogService->create($this->employee);
        $this->assertDatabaseCount('daily_time_records', 1); // Check that the generated sample record exists in the db
        $dtrId = $tl->dailyTimeRecord->id;

        $q = $this->individual->first_name;
        $result = $this->dailyTimeRecordService->searchTimeLogs($q, null, null, true);

        // Compare result to the expected types of response from the service and the name should match with the query
        if ($result instanceof Collection || $result instanceof Paginator || $result instanceof LengthAwarePaginator || $result instanceof CursorPaginator) {
            $logs = ($result instanceof Collection) ? $result : $result->items();

            foreach ($logs as $log) {
                $this->assertStringContainsString($q, $log['first_name']);
            }
        }
    }

    /**
     * Test current warm bodies can be accurately counted
     */
    public function test_can_count_warm_bodies(): void
    {
        // Call the service
        $result = $this->dailyTimeRecordService->countWarmBodies();

        $this->assertSame(0, $result['in_office']); // Assert that there is no employee in the office.

        $tl = $this->timeLogService->create($this->employee); // Generate time logs. Now an employee is in the office.
        $this->assertDatabaseCount('daily_time_records', 1); // Check that the generated sample record exists in the db

        // Call the service again.
        $result = $this->dailyTimeRecordService->countWarmBodies();
        $this->assertSame(1, $result['in_office']); // Assert that the log time registers and now there is an employee in the office.

    }
}
