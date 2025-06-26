<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ComprehensiveRecords\Employee;
use App\Models\DailyTimeRecords\DailyTimeRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;

class EmployeePolicy
{
    /* -------------------------------------------------------------------------- */
    /*                    Daily Time Record Authorization Logic                   */
    /* -------------------------------------------------------------------------- */

    // Roles and what they can do:
    // 1. PAS - Update All, View All, View specific record,
    // 2. Standard User - Update and View own record

    /**
     * Determine whether the user can view the employee's DTR.
     * HR PAS admin can view all records.
     * Standard Users can only view their own records.
     */
    public function viewDtrPerMonth(User $user, Employee $employee): bool
    {
        if ($user->hasAnyRole([Role::ADMIN->value, Role::HR_PAS_ADMIN->value, Role::SUPER_USER])) {
            return true;
        }

        return $user->id === $employee->individualBasicDetail->userProfile->user_id;
    }

    /**
     * Get start and end date based on passed month and year.
     *
     * @return void
     */
    public function getStartEndDate(string $month): array
    {
        $expectedFormat = 'Y-m';

        $startDate = Carbon::createFromFormat($expectedFormat, $month)->startOfMonth();
        $endDate = Carbon::createFromFormat($expectedFormat, $month)->endOfMonth();

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
    }

    /**
     * Determine whether the user can update the records.
     */
    public function updateBulkDtr(User $user, Employee $employee, array $dtrIds, array $passedDates): bool
    {
        $dates = isset($passedDates['month']) ? $this->getStartEndDate($passedDates['month']) : [
            'startDate' => $passedDates['start_date'],
            'endDate' => $passedDates['end_date'],
        ];

        // Verify that the DTR ids on the request belongs to the employee and is within the given month
        $dtrs = DailyTimeRecord::whereIn('id', $dtrIds)->whereBetween('date', [$dates['startDate'], $dates['endDate']])->get();

        // Count how many Ids are passed. There will be instances that there will be no id passed for new records, which is why we filter for not null values.
        $passedIdsCount = count(array_filter($dtrIds, function ($value) {
            return ! is_null($value);
        }));

        if ($dtrs->count() != $passedIdsCount) {
            throw new AuthorizationException('One or more daily time records not found or invalid. Make sure that they also belong to the month or period given.');
        }

        foreach ($dtrs as $dtr) {
            if ($dtr->employee->id != $employee->id) {
                throw new AuthorizationException('One or more daily time records does not belong to the employee.');
            }
        }

        if ($user->hasAnyRole([Role::ADMIN->value, Role::HR_PAS_ADMIN->value, Role::SUPER_USER])) {
            return true; // give permission to update records if user's role is HR PAS admin
        }

        return $user->id === $employee->individualBasicDetail->userProfile->user_id;
    }
}
