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
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorClass;
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

        /** @var LengthAwarePaginatorClass $paginator */
        $paginator = $this->buildPagination(PaginationType::LENGTH_AWARE, $query);

        $cloudStorageInstance = $this->cloudStorage;

        // Transform each dtr in the paginator to:
        //      - construct employee full name
        //      - generate temporary URL (valid for 24 hrs)
        return $paginator->through(function ($dtr) use ($cloudStorageInstance) {
            $dtr->employee_name = trim("{$dtr->first_name} {$dtr->last_name} {$dtr->ext_name}");

            $dtr->captured_image_url = $dtr->captured_image_path
                ? $cloudStorageInstance->generateTmpUrl($dtr->captured_image_path, 86400) // 24 hrs in seconds
                : null;

            return $dtr;
        });
    }

    /** {@inheritDoc} */
    public function countWarmBodies(): array
    {
        $date = now()->toDateString();

        $officeId = request('office');
        $divisionId = request('division');
        $sectionId = request('section');

        $employees = DB::table('employees as e')
            ->join('offices as o', 'e.office_id', '=', 'o.id')
            ->join('divisions as d', 'e.division_id', '=', 'd.id')
            ->join('section_or_units as s', 'e.section_or_unit_id', '=', 's.id')
            ->whereNull('e.deleted_at');

        if ($officeId) {
            $employees->where('e.office_id', $officeId);
            $divisionId = null;
            $sectionId = null;
        }

        if ($divisionId && ! $officeId) {
            $employees->where('e.division_id', $divisionId);
            $sectionId = null;
        }

        if ($sectionId && ! $officeId && ! $divisionId) {
            $employees->where('e.section_or_unit_id', $sectionId);
        }

        $latestLogs = DB::table('time_logs as tl')
            ->join('daily_time_records as dtr', 'tl.daily_time_record_id', '=', 'dtr.id')
            ->whereDate('dtr.date', $date)
            ->select('dtr.employee_id', DB::raw('MAX(tl.id) as latest_log_id'))
            ->groupBy('dtr.employee_id');

        $records = $employees
            ->leftJoinSub($latestLogs, 'latest_logs', 'e.id', '=', 'latest_logs.employee_id')
            ->leftJoin('time_logs as tl', 'tl.id', '=', 'latest_logs.latest_log_id')
            ->select(
                'e.id as employee_id',
                'e.office_id',
                'e.division_id',
                'e.section_or_unit_id',
                'o.name as office_name',
                'd.name as division_name',
                's.name as section_name',
                DB::raw('COALESCE(tl.is_in, 0) as is_in')
            )
            ->get();

        $perSection = $records->groupBy('section_or_unit_id')->map(function ($group) {
            return [
                'office_id' => $group->first()->office_id,
                'office_name' => $group->first()->office_name,
                'division_id' => $group->first()->division_id,
                'division_name' => $group->first()->division_name,
                'section_id' => $group->first()->section_or_unit_id,
                'section_name' => $group->first()->section_name,
                'total_employees' => $group->count(),
                'in_office' => $group->where('is_in', 1)->count(),
                'out_of_office' => $group->where('is_in', 0)->count(),
            ];
        })->values();

        $perDivision = $perSection->groupBy('division_id')->map(function ($sections) {
            return [
                'office_id' => $sections->first()['office_id'],
                'office_name' => $sections->first()['office_name'],
                'division_id' => $sections->first()['division_id'],
                'division_name' => $sections->first()['division_name'],
                'total_employees' => $sections->sum('total_employees'),
                'in_office' => $sections->sum('in_office'),
                'out_of_office' => $sections->sum('out_of_office'),
                'sections' => $sections->values(),
            ];
        })->values();

        return [
            'date' => $date,
            'total_employees' => $perSection->sum('total_employees'),
            'in_office' => $perSection->sum('in_office'),
            'out_of_office' => $perSection->sum('out_of_office'),
            'per_division' => $perDivision,
            'per_section' => $perSection,
        ];
    }

    /** {@inheritDoc} */
    public function countWarmBodiesPerStation(): array
    {
        $date = now()->toDateString();
        $targetOfficeId = request('office');

        $latestLogIds = DB::table('time_logs as tl')
            ->join('daily_time_records as dtr', 'tl.daily_time_record_id', '=', 'dtr.id')
            ->whereDate('dtr.date', $date)
            ->select('dtr.employee_id', DB::raw('MAX(tl.id) as max_log_id'))
            ->groupBy('dtr.employee_id');

        $query = DB::table('employees as e')
            ->whereNull('e.deleted_at')
            ->leftJoinSub($latestLogIds, 'latest', 'e.id', '=', 'latest.employee_id')
            ->leftJoin('time_logs as log_data', 'latest.max_log_id', '=', 'log_data.id')
            ->select([
                'e.id as employee_id',
                DB::raw('CASE 
                    WHEN log_data.is_in = 1 AND log_data.office_id IS NOT NULL THEN log_data.office_id 
                    ELSE e.office_id 
                END as effective_office_id'),
                'log_data.is_in',
            ]);

        if ($targetOfficeId) {
            $query->where(function ($q) use ($targetOfficeId) {
                $q->where(DB::raw('CASE 
                    WHEN log_data.is_in = 1 AND log_data.office_id IS NOT NULL THEN log_data.office_id 
                    ELSE e.office_id 
                END'), $targetOfficeId);
            });
        }

        $results = $query->get();

        return [
            'office_id' => $targetOfficeId,
            'total_employees' => $results->count(),
            'present' => $results->where('is_in', 1)->count(),
            'absent' => $results->where('is_in', 0)->count(),
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

    /** {@inheritDoc} */
    public function checkLate(Employee $employee): bool
    {
        $today = now()->toDateString();
        $timeLog = TimeLog::whereDate('date', $today)
            ->whereHas('dailyTimeRecord', function (Builder $query) use ($employee) {
                $query->where('employee_id', $employee->id);
            })
            ->oldest()
            ->first();

        if (! $timeLog) {
            return false;
        }

        $carbonScannedTime = Carbon::parse("$today $timeLog->scanned_time");

        $lateCutoff = now()->copy()->setTimeFromTimeString(now()->dayOfWeek === Carbon::MONDAY ? '08:00' : '09:00');

        if ($carbonScannedTime->greaterThan($lateCutoff)) {
            return true;
        }

        return false;
    }
}
