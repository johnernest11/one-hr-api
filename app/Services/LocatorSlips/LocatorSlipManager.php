<?php

namespace App\Services\LocatorSlips;

use App\Models\ComprehensiveRecords\Employee;
use App\Models\LocatorSlip\LocatorSlip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LocatorSlipManager
{
    /**
     * Create an Locator Slip
     */
    public function create(Employee $employee, array $request): LocatorSlip;

    /**
     * Fetch an employee's locator slips
     */
    public function viewEmployeeLocator(Employee $employee): LengthAwarePaginator;

    /**
     * Fetch a single Locator Slip
     */
    public function read(LocatorSlip|int $locatorSlip): LocatorSlip;

    /**
     * Fetch all Locator Slips and group by employee
     */
    public function readGrouped(): LengthAwarePaginator;

    /**
     * Update a Locator Slip
     */
    public function update(LocatorSlip $locatorSlip, array $newLocatorSlipInfo): LocatorSlip;
}
