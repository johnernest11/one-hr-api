<?php

namespace App\Services\DailyTimeRecords;

use App\Models\ComprehensiveRecords\Employee;
use App\Models\DailyTimeRecords\TimeLog;

interface TimeLogManager
{
    /**
     * Create a time log by scanning the employee's QR code.
     */
    public function create(Employee $employee): TimeLog;
}
