<?php

namespace App\Services\DailyTimeRecords;

use App\Models\ComprehensiveRecords\Employee;
use App\Models\DailyTimeRecords\QrCode;

interface QrCodeManager
{
    /**
     * Create a QR Code for the selected employee
     */
    public function create(Employee $employee): QrCode;

    /**
     * Fetch a single QR code. Return null if the QrCode does not exist.
     */
    public function read(Employee $employee): ?QrCode;

    /**
     * Update QR Code
     */
    public function update(Employee $employee, array $newQrInfo): QrCode;

    /**
     * Verify if QR belongs to an employee
     */
    public function verifyQr(array $request): Employee;
}
