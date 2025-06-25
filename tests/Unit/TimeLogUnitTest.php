<?php

namespace Tests\Unit;

use App\Models\ComprehensiveRecords\Employee;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\DailyTimeRecords\TimeLog;
use App\Services\DailyTimeRecords\TimeLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeLogUnitTest extends TestCase
{
    use RefreshDatabase;

    private TimeLogService $timeLogService;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->timeLogService = new TimeLogService(new TimeLog());
        $individual = IndividualBasicDetail::factory()->create();
        $this->employee = Employee::whereBelongsTo($individual)->firstOrFail();
    }

    /**
     * Test if a Time Log will be generated via passed employee
     */
    public function test_can_create_new_time_log_via_passed_employee(): void
    {
        $this->timeLogService->create($this->employee);
        $this->assertDatabaseCount('time_logs', 1);
    }
}
