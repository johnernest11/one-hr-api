<?php

namespace App\Services\LocatorSlips;

use App\Enums\PaginationType;
use App\Models\ComprehensiveRecords\Employee;
use App\Models\LocatorSlip\LocatorSlip;
use App\Models\LocatorSlip\LocatorSlipLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\CursorPaginator;

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

    /**
     * Search for Locator Slips
     */
    public function search(
        Employee $employee,
        string $term,
        ?PaginationType $pagination = null
    ): Collection|Paginator|LengthAwarePaginator|CursorPaginator;

    /**
     * Search for Locator Slips on All Employees
     */
    public function searchAll(
        string $term,
        ?PaginationType $pagination = null
    ): Collection|Paginator|LengthAwarePaginator|CursorPaginator;

    /**
     * Check if there is an active locator slip log
     */
    public function checkActiveLog(Employee $employee): ?LocatorSlipLogger;

    /**
     * Generate a Locator Slip
     */
    public function generate(Employee $employee, LocatorSlip $locatorSlip): array;
}
