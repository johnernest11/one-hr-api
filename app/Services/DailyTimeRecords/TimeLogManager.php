<?php

namespace App\Services\DailyTimeRecords;

use App\Models\ComprehensiveRecords\Employee;
use App\Models\DailyTimeRecords\TimeLog;
use App\Models\Libraries\Office;

interface TimeLogManager
{
    /**
     * Create a time log by scanning the employee's QR code.
     *
     * @param  string|\Illuminate\Http\UploadedFile|null  $capturedImage  Optional captured image file or path
     */
    public function create(Employee $employee, $capturedImage, Office $office): TimeLog;
}
