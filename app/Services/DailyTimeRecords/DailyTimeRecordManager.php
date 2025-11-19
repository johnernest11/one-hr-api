<?php

namespace App\Services\DailyTimeRecords;

use App\Enums\PaginationType;
use App\Models\ComprehensiveRecords\Employee;
use App\Models\DailyTimeRecords\TimeLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\CursorPaginator;

interface DailyTimeRecordManager
{
    /**
     * Fetch all time logs base on filter
     */
    public function all(): LengthAwarePaginator;

    /**
     * Count how many employees are currently inside the office and how many are not.
     * This will consider employees that did not go to the office today.
     */
    public function countWarmBodies(): array;

    /**
     * View daily time records per month.
     */
    public function viewDtrPerPeriodRange(Employee $employee, array $request): LengthAwarePaginator;

    /**
     * View warm bodies for today.
     */
    public function viewWarmBodiesToday(): LengthAwarePaginator;

    /**
     * Update daily time record/s for a specific month and their corresponding time logs.
     * Can also create new daily time record/s.
     */
    public function update(Employee $employee, array $newDtrInfo): Collection;

    /**
     * Search for Time Logs via last_name, first_name, and middle_name
     */
    public function searchTimeLogs(
        string $term,
        ?PaginationType $pagination,
        ?int $limit,
        bool $isMyProfile
    ): Collection|Paginator|LengthAwarePaginator|CursorPaginator;

    /**
     * Generate PDF of daily time records
     */
    public function generate(Employee $employee, string $startDate, string $endDate, string $sort = 'asc'): array;

    /**
     * Get user's last time log
     */
    public function getLastTimeLog(Employee $employee): ?TimeLog;

    /**
     * Check if employee is late
     */
    public function checkLate(Employee $employee): bool;
}
