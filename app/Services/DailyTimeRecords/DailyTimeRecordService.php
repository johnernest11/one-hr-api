<?php

namespace App\Services\DailyTimeRecords;

use App\Enums\PaginationType;
use App\Models\ComprehensiveRecords\Employee;
use App\Models\DailyTimeRecords\DailyTimeRecord;
use App\Models\DailyTimeRecords\TimeLog;
use App\Traits\Services\CanBuildPagination;
use Arr;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

class DailyTimeRecordService implements DailyTimeRecordManager
{
    use CanBuildPagination;

    public const MAX_TRANSACTION_DEADLOCK_ATTEMPTS = 5;

    private const MAX_SELECTED_TIMELOGS = 4;

    private DailyTimeRecord $model;

    public function __construct(DailyTimeRecord $model)
    {
        $this->model = $model;
    }

    /** {@inheritDoc} */
    public function all(): LengthAwarePaginator
    {
        /** @var Builder $dailyTimeRecord */
        $query = $this->model->filtered();

        return $this->buildPagination(PaginationType::LENGTH_AWARE, $query);
    }

    /** {@inheritDoc} */
    public function countWarmBodies(): array
    {
        $date = now()->toDateString();

        /* ---------- 1. latest log per employee ---------- */
        $latestLogSub = DB::table('time_logs as tl')
            ->join('daily_time_records as dtr', 'tl.daily_time_record_id', '=', 'dtr.id')
            ->select('dtr.employee_id', 'tl.is_in')
            ->whereDate('dtr.date', $date)
            ->whereRaw('tl.id = (                     
                SELECT MAX(tl2.id)
                FROM time_logs tl2
                JOIN daily_time_records dtr2 ON dtr2.id = tl2.daily_time_record_id
                WHERE dtr2.employee_id = dtr.employee_id
            )');

        /* ---------- 2. section‑level roll‑up ---------- */
        $perSection = DB::table('employees as e')
            ->join('divisions as d', 'e.division_id', '=', 'd.id')
            ->join('section_or_units as s', 'e.section_or_unit_id', '=', 's.id')
            ->leftJoinSub($latestLogSub, 'latest_logs', 'e.id', '=', 'latest_logs.employee_id')
            ->groupBy('e.division_id', 'd.name', 's.id', 's.name')
            ->selectRaw('
                e.division_id          as division_id,
                d.name                 as division_name,
                s.id                   as section_id,
                s.name                 as section_name,
                COUNT(e.id)                                                                as total_employees,
                CAST(SUM(CASE WHEN latest_logs.is_in = 1 THEN 1 ELSE 0 END) AS UNSIGNED)   as in_office,
                CAST(SUM(CASE WHEN latest_logs.is_in = 0 OR latest_logs.is_in IS NULL
                        THEN 1 ELSE 0 END) AS UNSIGNED)                                    as out_of_office
            ')
            ->get();

        /* ---------- 3. division‑level roll‑up ---------- */
        $perDivision = $perSection
            ->groupBy('division_id')
            ->map(function ($sections, $divisionId) {
                return [
                    'division_id' => $divisionId,
                    'division_name' => $sections->first()->division_name,
                    'total_employees' => $sections->sum('total_employees'),
                    'in_office' => $sections->sum('in_office'),
                    'out_of_office' => $sections->sum('out_of_office'),
                    'sections' => $sections->values(),
                ];
            })
            ->values();

        /* ---------- 4. grand totals ---------- */
        $totals = [
            'total_employees' => $perSection->sum('total_employees'),
            'in_office' => $perSection->sum('in_office'),
            'out_of_office' => $perSection->sum('out_of_office'),
        ];

        /* ---------- 5. final payload ---------- */
        return [
            'date' => $date,
            ...$totals,
            'per_division' => $perDivision,
            'per_section' => $perSection,
        ];
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

    /** {@inheritDoc} */
    public function viewDtrPerPeriodRange(Employee $employee, array $request): LengthAwarePaginator
    {
        $dates = isset($request['month']) ? $this->getStartEndDate($request['month']) : [
            'startDate' => $request['start_date'],
            'endDate' => $request['end_date'],
        ];

        // Create a query for getting the DTRs between the start and end date.
        $query = $this->model->query()->whereBetween('date', [$dates['startDate'], $dates['endDate']])->where('employee_id', $employee->id)->with('timeLog');

        return $this->buildPagination(PaginationType::LENGTH_AWARE, $query);

    }

    /** {@inheritDoc} */
    public function viewWarmBodiesToday(): LengthAwarePaginator
    {
        request()->merge(['date' => Carbon::today()->toDateString()]);

        /** @var Builder $dailyTimeRecord */
        $query = $this->model->filtered()->currentlyInsideOnly();

        return $this->buildPagination(PaginationType::LENGTH_AWARE, $query);

    }

    /** {@inheritDoc} */
    public function update(Employee $employee, array $request): Collection
    {
        return DB::transaction(function () use ($employee, $request) {
            // Initialize empty collection for returning updated records.
            $updatedDtrs = new Collection();

            // Get status and update all dtrs.
            $newStatus = isset($request['status']) ? $request['status'] : null;

            foreach ($request['dtr'] as $dtrInfo) {
                // Extract ID if present, otherwise it's a new record
                $id = $dtrInfo['id'] ?? null;

                // Update existing DTR if id exists in the request
                if ($id) {
                    $dtr = $this->model->findOrFail($dtrInfo['id']); // Check if record exists

                    if ($newStatus) {
                        $dtrInfo['status'] = $newStatus;
                    }

                    // Update time_logs if it is passed
                    if (isset($dtrInfo['time_logs'])) {
                        // Get current count of selected time logs
                        $currentSelectedCount = $dtr->timeLog->where('is_selected', true)->count();
                        $newSelectedCount = 0;

                        foreach ($dtrInfo['time_logs'] as $timeLogInfo) {
                            // Verify that the passed time log belongs to the current dtr.
                            $belongsToDtr = TimeLog::where('daily_time_record_id', $id)->where('id', $timeLogInfo['id'])->exists();
                            if (! $belongsToDtr) {
                                throw new Exception("One or more time logs do not belong to the daily time record ID: $id.");
                            }

                            // Verify that the incoming is_selected changes is the opposite of the current value.
                            $currentSelection = TimeLog::where('id', $timeLogInfo['id'])->where('daily_time_record_id', $dtrInfo['id'])->value('is_selected');

                            if ($timeLogInfo['is_selected'] && $currentSelection != $timeLogInfo['is_selected']) {
                                $newSelectedCount++;
                            } elseif (! $timeLogInfo['is_selected'] && $currentSelection != $timeLogInfo['is_selected']) {
                                $newSelectedCount--;
                            }
                        }
                        // Check that there is always a maximum of 4 selected for this dtr.
                        // Compare current and new updates if they exceed the maximum limit
                        if ($currentSelectedCount + $newSelectedCount > self::MAX_SELECTED_TIMELOGS) {
                            throw new Exception('Exceeded maximum number of selected time logs. Maximum: '.self::MAX_SELECTED_TIMELOGS);
                        }

                        // Update time logs after all validations are passed.
                        foreach ($dtrInfo['time_logs'] as $timeLogInfo) {
                            TimeLog::where('id', $timeLogInfo['id'])->where('daily_time_record_id', $dtrInfo['id'])->update(Arr::except($timeLogInfo, ['id']));
                        }
                    }

                    // Update existing record if ID exists
                    $dtr->update(Arr::except($dtrInfo, ['id'])); // Exclude id
                    $dtr->refresh()->with('timeLog');
                    $updatedDtrs->push($dtr);
                } else {
                    // Validate that the passed date is not already taken.
                    $dateIsTaken = $this->model->where('date', $dtrInfo['date'])->where('employee_id', $employee->id)->exists();

                    if ($dateIsTaken) {
                        throw new Exception($dtrInfo['date'].' already has a daily time record.');
                    }

                    // For the status of the dtr, copy the passed status in the request.
                    if ($newStatus) {
                        $dtrInfo['status'] = $newStatus;
                    }

                    $dtrInfo['employee_id'] = $employee->id;

                    $dtr = $this->model->create($dtrInfo);
                    $updatedDtrs->push($dtr);
                }

            }

            // Return collection of updated records.
            return $updatedDtrs;

        }, self::MAX_TRANSACTION_DEADLOCK_ATTEMPTS);

    }

    /** {@inheritDoc} */
    public function searchTimeLogs(
        string $term,
        ?PaginationType $pagination,
        ?int $limit,
        bool $isMyProfile
    ): Collection|Paginator|LengthAwarePaginator|CursorPaginator {
        // Consider where this API is being called. If it is on the My Profile view, then the date should always be the current date.
        // If it is on PAS dashboard, then this can be chained with all the filters.
        if ($isMyProfile) {
            request()->merge(['date' => Carbon::today()->toDateString()]); // Use request() since passing the request for merging here does not work for some reason.
            $query = $this->model->filtered()->currentlyInsideOnly(); // Filter for the employees currently inside if query is in my profile
        } else {
            // Filter first, then query for name.
            /** @var Builder $dailyTimeRecord */
            $query = $this->model->filtered();
        }
        // @todo This can be a fulltext search, however it is not optimized for tests. So for now, we will compare by using like.
        $updatedQuery = $query->where(function (Builder $q) use ($term) {
            $q->where('individual_basic_details.first_name', 'like', "%$term%")
                ->orWhere('individual_basic_details.last_name', 'like', "%$term%")
                ->orWhere('individual_basic_details.middle_name', 'like', "%$term%");
        });

        return $this->buildPagination($pagination, $updatedQuery, $limit);
    }
}
