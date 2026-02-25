<?php

namespace Tests\Unit;

use App\Models\ComprehensiveRecords\Employee;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\DailyTimeRecords\TimeLog;
use App\Models\Libraries\Office;
use App\Services\CloudStorageServices\AwsS3StorageService;
use App\Services\DailyTimeRecords\TimeLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TimeLogUnitTest extends TestCase
{
    use RefreshDatabase;

    private TimeLogService $timeLogService;

    private Employee $employee;

    private Office $office;

    private UploadedFile $fakeImage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        // Use the concrete implementation directly
        $awsS3Service = new AwsS3StorageService;

        // Pass it to the TimeLogService
        $this->timeLogService = new TimeLogService(new TimeLog, $awsS3Service);

        $this->office = Office::first();
        $this->fakeImage = UploadedFile::fake()->image('fake_image.jpg', 500, 500);

        $individual = IndividualBasicDetail::factory()->create();
        $this->employee = Employee::whereBelongsTo($individual)->firstOrFail();
    }

    /** @test */
    public function test_can_create_new_time_log_via_passed_employee(): void
    {
        $this->timeLogService->create($this->employee, $this->fakeImage, $this->office);

        $this->assertDatabaseCount('time_logs', 1);
    }

    /** @test */
    public function test_it_can_save_capture_image(): void
    {
        $employeeId = 1;
        $dateToday = now()->toDateString();
        $path = "images/timelog/{$dateToday}/{$employeeId}";
        $fileName = 'fake_image.jpg';

        $awsS3Service = new AwsS3StorageService;
        $file = UploadedFile::fake()->image($fileName);
        $mimeType = $file->getMimeType();
        $content = $file->getRealPath();
        $base64 = base64_encode(file_get_contents($content));

        $dataUri = "data:$mimeType;base64,".$base64;

        $fullPath = $awsS3Service->upload($path, $dataUri, $fileName);

        $this->assertEquals($fileName, basename($fullPath));
        $this->assertEquals($path, dirname($fullPath));
    }
}
