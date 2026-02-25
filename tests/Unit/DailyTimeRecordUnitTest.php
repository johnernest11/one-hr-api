<?php

namespace Tests\Unit;

use App\Models\ComprehensiveRecords\Employee;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\DailyTimeRecords\DailyTimeRecord;
use App\Models\DailyTimeRecords\TimeLog;
use App\Models\Libraries\Office;
use App\Services\CloudStorageServices\AwsS3StorageService;
use App\Services\DailyTimeRecords\DailyTimeRecordService;
use App\Services\DailyTimeRecords\TimeLogService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Mockery;
use Tests\TestCase;

class DailyTimeRecordUnitTest extends TestCase
{
    use RefreshDatabase;

    private DailyTimeRecordService $dailyTimeRecordService;

    private TimeLogService $timeLogService;

    private Employee $employee;

    private DailyTimeRecord $dailyTimeRecord;

    private IndividualBasicDetail $individual;

    private UploadedFile $fakeImage;

    private Office $office;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');

        $mockStorage = Mockery::mock(AwsS3StorageService::class);
        $mockStorage->shouldReceive('upload')->andReturn('mocked/path/file.jpg');
        $mockStorage->shouldReceive('delete')->andReturn(true);
        $mockStorage->shouldReceive('generateTmpUrl')->andReturn('https://fake-s3-url.com/image.jpg');

        $this->fakeImage = UploadedFile::fake()->image('fake_image.jpg', 500, 500);
        $this->office = Office::first();
        $this->dailyTimeRecordService = new DailyTimeRecordService(new DailyTimeRecord, $mockStorage);
        $this->timeLogService = new TimeLogService(new TimeLog, $mockStorage);

        $this->individual = IndividualBasicDetail::factory()->create();
        $this->employee = Employee::whereBelongsTo($this->individual)->firstOrFail();
    }

    /**
     * Test if all Time Logs can be viewed via the service
     */
    public function test_can_view_all_time_logs(): void
    {
        $tl = $this->timeLogService->create($this->employee, $this->fakeImage, $this->office);
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
        $tl = $this->timeLogService->create($this->employee, $this->fakeImage, $this->office);
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
        $tl = $this->timeLogService->create($this->employee, $this->fakeImage, $this->office);
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
        $tl = $this->timeLogService->create($this->employee, $this->fakeImage, $this->office);
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
        $tl = $this->timeLogService->create($this->employee, $this->fakeImage, $this->office);
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

        $tl = $this->timeLogService->create($this->employee, $this->fakeImage, $this->office); // Generate time logs. Now an employee is in the office.
        $this->assertDatabaseCount('daily_time_records', 1); // Check that the generated sample record exists in the db

        // Call the service again.
        $result = $this->dailyTimeRecordService->countWarmBodies();
        $this->assertSame(1, $result['in_office']); // Assert that the log time registers and now there is an employee in the office.

    }

    /**
     * Test DTR PDF can be generated for a given employee and date range
     */
    public function test_can_generate_dtr_pdf_for_given_employee_and_date_range(): void
    {
        $employee = $this->employee;

        // Create some DTRs with time logs
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
            ]);
        }

        $startDate = '2025-09-01';
        $endDate = '2025-09-05';

        $result = $this->dailyTimeRecordService->generate($employee, $startDate, $endDate);

        $this->assertNotEmpty($result['fileContent'], 'PDF content should not be empty');
        $expectedFileName = "DTR-{$employee->id}-{$startDate}_to_{$endDate}.pdf";
        $this->assertEquals($expectedFileName, $result['fileName']);

    }
}
