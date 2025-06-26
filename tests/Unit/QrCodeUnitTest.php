<?php

namespace Tests\Unit;

use App\Models\ComprehensiveRecords\Employee;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\DailyTimeRecords\QrCode;
use App\Services\DailyTimeRecords\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrCodeUnitTest extends TestCase
{
    use RefreshDatabase;

    private QrCodeService $qrCodeService;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->qrCodeService = new QrCodeService(new QrCode());
        $individual = IndividualBasicDetail::factory()->create();
        $this->employee = Employee::whereBelongsTo($individual)->firstOrFail();
    }

    /**
     * Test if a QR code will be generated via the service
     */
    public function test_can_generate_qr_code(): void
    {
        $this->qrCodeService->create($this->employee);
        $this->assertDatabaseCount('qr_codes', 1);
    }

    /**
     * Test if the status of QR code can be edited via the service
     */
    public function test_can_edit_qr_code(): void
    {
        $qrCode = $this->qrCodeService->create($this->employee);
        $this->assertDatabaseCount('qr_codes', 1);

        $newInfo = ['is_active' => false];

        $updatedQr = $this->qrCodeService->update($this->employee, $newInfo);
        $this->assertEquals(false, $updatedQr->is_active);
    }

    /**
     * Test if a QR will be displayed based on passed employee id.
     */
    public function test_can_view_qr_code_by_employee_id(): void
    {
        $qrCode = $this->qrCodeService->create($this->employee);
        $this->assertDatabaseCount('qr_codes', 1);

        $result = $this->qrCodeService->read($this->employee);
        $this->assertEquals($qrCode->id, $result->id);
    }

    /**
     * Test if passed scanned QR can be decrypted and show the correct employee.
     */
    public function test_can_verify_scanned_qr(): void
    {
        $qr = $this->qrCodeService->create($this->employee);
        $this->assertDatabaseCount('qr_codes', 1);

        $data = [
            'scanned_qr' => $qr->qr_code_value,
        ];
        $result = $this->qrCodeService->verifyQr($data);
        $this->assertEquals($qr->employee_id, $result->id);
    }
}
