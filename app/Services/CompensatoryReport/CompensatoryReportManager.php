<?php

namespace App\Services\CompensatoryReport;

use App\Models\CompensatoryReport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CompensatoryReportManager
{
    /**
     * Fetch all Compensatory Reports
     */
    public function all(): LengthAwarePaginator;

    /**
     * Fetch a single Compensatory Report
     */
    public function read(CompensatoryReport $compensatoryReport): CompensatoryReport;
}
