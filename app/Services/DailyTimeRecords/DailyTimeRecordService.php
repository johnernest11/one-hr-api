<?php

namespace App\Services\DailyTimeRecords;

use App\Enums\PaginationType;
use App\Models\ComprehensiveRecords\Employee;
use App\Models\DailyTimeRecords\DailyTimeRecord;
use App\Models\DailyTimeRecords\TimeLog;
use App\Models\Libraries\Division;
use App\Models\Libraries\SectionOrUnit;
use App\Services\CloudStorageServices\CloudStorageManager;
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

    protected DailyTimeRecord $model;

    protected CloudStorageManager $cloudStorage;

    public const MAX_TRANSACTION_DEADLOCK_ATTEMPTS = 5;

    private const MAX_SELECTED_TIMELOGS = 4;

    public function __construct(DailyTimeRecord $model, CloudStorageManager $cloudStorage)
    {
        $this->model = $model;
        $this->cloudStorage = $cloudStorage;
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
            ->whereNull('e.deleted_at') // filter soft deleted employees
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
        request()->merge(['date' => now()->toDateString()]);

        /** @var Builder $dailyTimeRecord */
        $query = $this->model->filtered()->currentlyInsideOnly();

        $paginated = $this->buildPagination(PaginationType::LENGTH_AWARE, $query);

        foreach ($paginated as $record) {
            foreach ($record->timeLog ?? [] as $log) {
                if (! empty($log->captured_image_path)) {
                    try {
                        $log->captured_image_url = $this->cloudStorage->generateTmpUrl($log->captured_image_path, 3600);
                    } catch (\Throwable $e) {
                        \Log::warning("Failed to generate temporary URL for image: {$log->captured_image_path}");
                        $log->captured_image_url = null;
                    }
                } else {
                    $log->captured_image_url = null;
                }
            }
        }

        return $paginated;
    }

    /** {@inheritDoc} */
    public function update(Employee $employee, array $request): Collection
    {
        return DB::transaction(function () use ($employee, $request) {
            $updatedDtrs = new Collection;

            $newStatus = isset($request['status']) ? $request['status'] : null;

            foreach ($request['dtr'] as $dtrInfo) {
                $id = $dtrInfo['id'] ?? null;

                if ($id) {
                    $dtr = $this->model->findOrFail($dtrInfo['id']);

                    if ($newStatus) {
                        $dtrInfo['status'] = $newStatus;
                    }

                    if (! empty($dtrInfo['time_logs'])) {
                        foreach ($dtrInfo['time_logs'] as $timeLog) {
                            $timeLogInfo = is_array($timeLog) ? $timeLog : get_object_vars($timeLog);

                            if (empty($timeLogInfo['scanned_time'])) {
                                throw new \Exception("scanned_time is required for DTR id {$dtr->id}");
                            }

                            $isNew = empty($timeLogInfo['id']);
                            $timeLogInfo['daily_time_record_id'] = $dtr->id;
                            $timeLogInfo['date'] = $timeLogInfo['date'] ?? $dtr->date->format('Y-m-d');
                            $timeLogInfo['is_in'] = $timeLogInfo['is_in'] ?? false;
                            $timeLogInfo['is_selected'] = $timeLogInfo['is_selected'] ?? false;

                            if ($isNew) {
                                unset($timeLogInfo['id']);
                                TimeLog::create($timeLogInfo);
                            } else {
                                TimeLog::where('id', $timeLogInfo['id'])
                                    ->where('daily_time_record_id', $dtr->id)
                                    ->update(Arr::except($timeLogInfo, ['id']));
                            }
                        }

                    }

                    $dtr->update(Arr::except($dtrInfo, ['id']));
                    $dtr->refresh()->load('timeLog');
                    $updatedDtrs->push($dtr);
                } else {
                    $dateIsTaken = $this->model->where('date', $dtrInfo['date'])->where('employee_id', $employee->id)->exists();

                    if ($dateIsTaken) {
                        throw new Exception($dtrInfo['date'].' already has a daily time record.');
                    }

                    if ($newStatus) {
                        $dtrInfo['status'] = $newStatus;
                    }

                    $dtrInfo['employee_id'] = $employee->id;

                    $dtr = $this->model->create($dtrInfo);
                    $updatedDtrs->push($dtr);
                }

            }

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

    /** {@inheritDoc} */
    public function generate(Employee $employee, string $startDate, string $endDate, string $sort = 'asc'): array
    {
        // Fetch DTRs with time logs
        $dtrs = DailyTimeRecord::with('timeLog')
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', $sort)
            ->get();

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        // Generate all dates in the range
        $startFormatted = Carbon::parse($startDate)->format('F j, Y');
        $endFormatted = Carbon::parse($endDate)->format('F j, Y');

        $allRows = collect();

        for ($date = $start; $date->lte($end); $date->addDay()) {
            $dateStr = $date->format('Y-m-d');

            // Find existing DTR for this date
            $existing = $dtrs->first(function ($dtr) use ($dateStr) {
                return Carbon::parse($dtr->date)->format('Y-m-d') === $dateStr;
            });

            // Ensure timeLog is always a collection
            $allRows->push($existing ? (object) [
                'date' => $existing->date,
                'timeLog' => $existing->timeLog ?? collect(),
                'ut' => $existing->ut ?? 0,
                'ot' => $existing->ot ?? 0,
                'employee_remarks' => $existing->employee_remarks ?? '',
            ] : (object) [
                'date' => $dateStr,
                'timeLog' => collect(),
                'ut' => 0,
                'ot' => 0,
                'employee_remarks' => '',
            ]);
        }

        // Employee details
        $employeeDetail = $employee->individualBasicDetail;
        $fullName = strtoupper(trim("{$employeeDetail->last_name}, {$employeeDetail->first_name} {$employeeDetail->middle_name}"));
        $divisionName = optional($employee->division_id ? Division::find($employee->division_id) : null)->name ?? 'PLACEHOLDER';
        $sectionName = optional($employee->section_or_unit_id ? SectionOrUnit::find($employee->section_or_unit_id) : null)->name ?? 'PLACEHOLDER';
        $positionTitle = optional($employee->item->position)->title ?? 'PLACEHOLDER';

        // Pass to Blade
        $html = view('template.daily_time_record', [
            'period' => "From: {$startFormatted} To: {$endFormatted}",
            'fullName' => $fullName,
            'position' => $positionTitle,
            'dept_division' => $divisionName,
            'dept_section' => $sectionName,
            'allRows' => $allRows,
            'supervisorNotes' => null,
            'certifyingOfficer' => 'PLACEHOLDER',
            'officerPosition' => 'PLACEHOLDER',
        ])->render();

        $options = new \Dompdf\Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('Legal', 'portrait');
        $dompdf->render();

        return [
            'fileContent' => $dompdf->output(),
            'fileName' => "DTR-{$employee->id}-{$startDate}_to_{$endDate}.pdf",
        ];
    }

    /** {@inheritDoc} */
    public function getLastTimeLog(Employee $employee): ?TimeLog
    {
        $today = now()->toDateString();
        $timeLog = TimeLog::whereDate('date', $today)
            ->whereHas('dailyTimeRecord', function (Builder $query) use ($employee) {
                $query->where('employee_id', $employee->id);
            })
            ->latest()
            ->first();

        return $timeLog;
    }
}
