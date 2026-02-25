<?php

namespace App\Services\DailyTimeRecords;

use App\Enums\DocumentStatus;
use App\Events\TimeLogCreated;
use App\Models\ComprehensiveRecords\Employee;
use App\Models\DailyTimeRecords\DailyTimeRecord;
use App\Models\DailyTimeRecords\TimeLog;
use App\Models\Libraries\Office;
use App\Models\LocatorSlip\LocatorSlip;
use App\Models\LocatorSlip\LocatorSlipLogger;
use App\Services\CloudStorageServices\CloudStorageManager;
use App\Traits\Controllers\CanMoveCapturedImageToCloud;
use App\Traits\Services\CanBuildPagination;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TimeLogService implements TimeLogManager
{
    use CanBuildPagination;
    use CanMoveCapturedImageToCloud;

    public const MAX_TRANSACTION_DEADLOCK_ATTEMPTS = 5;

    private const MAX_SELECTED_TIMELOGS = 4;

    private TimeLog $model;

    private CloudStorageManager $cloudStorage;

    public function __construct(TimeLog $model, CloudStorageManager $cloudStorage)
    {
        $this->model = $model;
        $this->cloudStorage = $cloudStorage;
    }

    /**
     * Create a time log by scanning the employee's QR code.
     *
     * @param  UploadedFile|string|null  $capturedImage
     *
     * @throws Exception
     */
    public function create(Employee $employee, $capturedImage, Office $office): TimeLog
    {
        return DB::transaction(function () use ($employee, $capturedImage, $office) {
            $limit = config('timelog.duplicate_scan_limit');
            $dateToday = Carbon::now()->toDateString();

            $dtr = DailyTimeRecord::firstOrCreate(
                ['employee_id' => $employee->id, 'date' => $dateToday],
                ['status' => DocumentStatus::DRAFT->value]
            );

            $latestTimeLog = $this->model->whereBelongsTo($dtr)->latest('scanned_time')->first();
            if ($latestTimeLog) {
                $timeLogTime = Carbon::parse($latestTimeLog->scanned_time);
                if ($timeLogTime->diffInMinutes(Carbon::now()) <= $limit) {
                    throw new Exception('Duplicate scan.');
                }
                $isIn = ! $latestTimeLog->is_in;
            } else {
                $isIn = true;
            }

            $imagePath = null;
            if ($capturedImage instanceof UploadedFile) {
                $imagePath = $this->moveCapturedImageToCloud($capturedImage, $this->cloudStorage, $employee->id);
            } elseif (is_string($capturedImage)) {
                $imagePath = $capturedImage;
            }

            $timeLogData = [
                'date' => $dateToday,
                'scanned_time' => Carbon::now()->format('H:i'),
                'is_in' => $isIn,
                'captured_image_path' => $imagePath,
                'is_selected' => true,
                'office_id' => $office->id ?? null,
            ];

            $countIsSelected = $this->model->whereBelongsTo($dtr)->where('is_selected', true)->count();
            if ($countIsSelected >= self::MAX_SELECTED_TIMELOGS) {
                $timeLogData['is_selected'] = false;
            }

            $timeLog = $dtr->timeLog()->create($timeLogData);

            $timeLog->load([
                'dailyTimeRecord:id,employee_id,date,ut,is_edit_ut,ot,is_missing,employee_remarks,hr_remarks,status,created_at,updated_at',
                'dailyTimeRecord.employee:id,id_number,individual_basic_detail_id,item_id,division_id,section_or_unit_id',
                'dailyTimeRecord.employee.item:id,position_id,number,date_of_creation,status,date_filled_up,employment_status,fund_source_id',
                'dailyTimeRecord.employee.item.position:id,title,parenthetical_title,level,created_at,updated_at',
                'dailyTimeRecord.employee.item.fundSource:id,name,created_at,updated_at',
                'dailyTimeRecord.employee.division:id,name,head_user_id,added_by_user_id,last_modified_by_user_id,created_at,updated_at',
                'dailyTimeRecord.employee.sectionOrUnit:id,name,division_id,head_user_id,added_by_user_id,last_modified_by_user_id,created_at,updated_at',
                'dailyTimeRecord.employee.individualBasicDetail:id,first_name,last_name,middle_name,ext_name',
                'dailyTimeRecord.employee.individualBasicDetail.userProfile:id,individual_basic_detail_id,profile_picture_path',
            ]);

            $this->updateLocatorSlipLogger($employee, $timeLogData);

            // Fire broadcast
            try {
                broadcast(new TimeLogCreated($timeLog));
            } catch (Exception $e) {
                Log::error('Broadcasting failed: '.$e->getMessage());
            }

            return $timeLog;
        }, self::MAX_TRANSACTION_DEADLOCK_ATTEMPTS);
    }

    /**
     * Updates Locator Slip Logs Time Out/In.
     *
     * This expects the user to only have one locator slip logger active at a time.
     * If there are multiple, all of them will be updated, hence the loop.
     * This is to prevent issues from arising.
     */
    public function updateLocatorSlipLogger(Employee $employee, array $timeLog): void
    {
        DB::transaction(function () use ($employee, $timeLog) {
            $lastTimeLog = app(DailyTimeRecordService::class)->getLastTimeLog($employee);

            $carbonDate = Carbon::parse($timeLog['date']);
            $lsCollection = LocatorSlip::where('employee_id', $employee->id)
                ->whereYear('date', $carbonDate->year)
                ->whereMonth('date', $carbonDate->month)
                ->get();

            if (! $lsCollection) {
                return;
            }

            foreach ($lsCollection as $slip) {
                $lsLogs = LocatorSlipLogger::whereBelongsTo($slip)->where('date', $timeLog['date'])->get();

                if (! $lsLogs) {
                    return;
                }

                foreach ($lsLogs as $log) {
                    if (! $log->time_out) {
                        // Do not update locator slip if the last time log is an in, which means that the employee just went in the office and not out.
                        if (! $lastTimeLog) {
                            return;
                        }
                        if ($lastTimeLog) {
                            if ($lastTimeLog->is_in) {
                                return;
                            }
                        }

                        $log->update([
                            'time_out' => $timeLog['scanned_time'],
                        ]);
                    } elseif (! $log->time_in) {
                        $log->update([
                            'time_in' => $timeLog['scanned_time'],
                        ]);
                    }
                }
            }
        }, self::MAX_TRANSACTION_DEADLOCK_ATTEMPTS);
    }
}
