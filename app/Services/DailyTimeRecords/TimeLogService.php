<?php

namespace App\Services\DailyTimeRecords;

use App\Enums\DocumentStatus;
use App\Models\ComprehensiveRecords\Employee;
use App\Models\DailyTimeRecords\DailyTimeRecord;
use App\Models\DailyTimeRecords\TimeLog;
use App\Traits\Services\CanBuildPagination;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class TimeLogService implements TimeLogManager
{
    use CanBuildPagination;

    public const MAX_TRANSACTION_DEADLOCK_ATTEMPTS = 5;

    private const MAX_SELECTED_TIMELOGS = 4;

    private const DUPLICATE_SCAN_LIMIT_MINUTES = 1; //@todo adjust for testing. by default = 15 mins

    private TimeLog $model;

    public function __construct(TimeLog $model)
    {
        $this->model = $model;

    }

    /**
     * {@inheritDoc}
     */
    public function create(Employee $employee): TimeLog
    {
        return DB::transaction(function () use ($employee) {
            // Check if there's a DTR for today.
            // If none, create record. Skip checks for 15 minute delay since if there's no DTR, this entry will be the very first time log for today.
            try {
                $dateToday = Carbon::now()->toDateString();
                $dtr = DailyTimeRecord::whereBelongsTo($employee)->where('date', $dateToday)->firstOrFail();
            } catch (ModelNotFoundException $e) {
                $dtrData = [
                    'employee_id' => $employee->id,
                    'date' => $dateToday,
                    'status' => DocumentStatus::DRAFT->value,
                ];

                $dtr = DailyTimeRecord::create($dtrData);

                $timeLogData = [
                    'date' => $dateToday,
                    'scanned_time' => Carbon::now()->format('H:i'),
                    'is_in' => true,
                ];
                $timeLog = $dtr->timeLog()->create($timeLogData)
                    ->fresh([
                        'dailyTimeRecord.employee:id,id_number,individual_basic_detail_id,item_id',
                        'dailyTimeRecord.employee.item:id,position_id',
                        'dailyTimeRecord.employee.individualBasicDetail:id,first_name,last_name,middle_name,ext_name',
                        'dailyTimeRecord.employee.item.position:id,title',
                        'dailyTimeRecord.employee.individualBasicDetail.userProfile:id,individual_basic_detail_id,profile_picture_path',
                    ]);

                return $timeLog;
            }

            // Check if there's already a previous log 15 minutes before the current one.
            // If there is, disregard the following entry and throw exception to signify duplicate entry.
            // If not, create new time log.

            $latestTimeLog = $this->model->whereBelongsTo($dtr)->latest('scanned_time')->first();
            $timeLogTime = Carbon::parse($latestTimeLog->scanned_time);

            if ($timeLogTime->diffInMinutes(Carbon::now()) <= self::DUPLICATE_SCAN_LIMIT_MINUTES) {
                throw new Exception('Duplicate scan.');
            }

            $timeLogData = [
                'date' => $dateToday,
                'scanned_time' => Carbon::now()->format('H:i'),
                'is_in' => $latestTimeLog->is_in ? false : true, // If latest time log is true, current one will be false. And vice versa.
            ];

            // Check if there's already 4 is_selected=true for this day.
            // If there is, set the succeeding records as false.
            // If there is not, do nothing since the default value of the field is true.
            $countIsSelected = $this->model->whereBelongsTo($dtr)->where('is_selected', true)->count();

            if ($countIsSelected >= self::MAX_SELECTED_TIMELOGS) {
                $timeLogData['is_selected'] = false;
            }

            $timeLog = $dtr->timeLog()->create($timeLogData)
                ->fresh([
                    'dailyTimeRecord.employee:id,id_number,individual_basic_detail_id,item_id',
                    'dailyTimeRecord.employee.item:id,position_id',
                    'dailyTimeRecord.employee.individualBasicDetail:id,first_name,last_name,middle_name,ext_name',
                    'dailyTimeRecord.employee.item.position:id,title',
                    'dailyTimeRecord.employee.individualBasicDetail.userProfile:id,individual_basic_detail_id,profile_picture_path',
                ]);

            return $timeLog;
        }, self::MAX_TRANSACTION_DEADLOCK_ATTEMPTS);
    }
}
