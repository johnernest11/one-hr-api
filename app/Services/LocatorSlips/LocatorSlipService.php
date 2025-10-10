<?php

namespace App\Services\LocatorSlips;

use App\Enums\EmploymentStatus;
use App\Enums\LocatorFormType;
use App\Enums\PaginationType;
use App\Enums\Period;
use App\Models\ComprehensiveRecords\Employee;
use App\Models\DailyTimeRecords\DailyTimeRecord;
use App\Models\LocatorSlip\LocatorSlip;
use App\Models\LocatorSlip\LocatorSlipLogger;
use App\Services\DailyTimeRecords\DailyTimeRecordManager;
use App\Traits\Services\CanBuildPagination;
use Arr;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Str;

class LocatorSlipService implements LocatorSlipManager
{
    use CanBuildPagination;

    public const MAX_TRANSACTION_DEADLOCK_ATTEMPTS = 5;

    private LocatorSlip $model;

    private DailyTimeRecordManager $dailyTimeRecordService;

    public function __construct(LocatorSlip $model, DailyTimeRecordManager $dailyTimeRecordService)
    {
        $this->model = $model;
        $this->dailyTimeRecordService = $dailyTimeRecordService;
    }

    /**
     * {@inheritDoc}
     */
    public function create(Employee $employee, array $request): LocatorSlip
    {
        return DB::transaction(function () use ($employee, $request) {
            $today = Carbon::now();
            $empStatus = $employee->item->employment_status;

            /* -------------------------------------------------------------------------- */
            /*                     Handle Locator Slip No. Generation */
            /* -------------------------------------------------------------------------- */
            $formType = Str::lower($request['form_type']);
            $empLastLS = LocatorSlip::where('employee_id', $employee->id)->where('form_type', $formType)->latest()->first();

            if ($formType == LocatorFormType::FORM_C->value) {
                $lsNoPrefix = $today->format('mY');
                $lastLS = LocatorSlip::latest()->where('form_type', LocatorFormType::FORM_C->value)->first();

                if (! $lastLS) {
                    $nextNumber = 1;
                } else {
                    if ($empStatus == EmploymentStatus::CONTRACT_OF_SERVICE) {
                        if ($empLastLS) {
                            $isFirstPeriodLastLS = Carbon::parse($empLastLS->date)->day <= 15;
                            $isFirstPeriodCurrent = $today->day <= 15;
                            $isSameMonthAndYear = Carbon::parse($empLastLS->date)->year === $today->year && Carbon::parse($empLastLS->date)->month === $today->month;
                            if ($isFirstPeriodLastLS == $isFirstPeriodCurrent && $isSameMonthAndYear) {
                                throw new Exception('A Locator Slip Form C has already been created for this period.');
                            }
                        }
                    } else {
                        if ($empLastLS) {
                            if (Carbon::parse($empLastLS->date)->month == $today->month) {
                                throw new Exception('A Locator Slip Form C has already been created for this month.');
                            }
                        }
                    }

                    $lastNumber = intval(substr($lastLS->locator_slip_no, 6));
                    $nextNumber = $lastNumber + 1;
                }

                $paddedNumber = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
                $request['locator_slip_no'] = $lsNoPrefix.$paddedNumber;
            } else {
                if ($empLastLS) {
                    if (Carbon::parse($empLastLS->date)->month == $today->month) {
                        throw new Exception('A Locator Slip Form A has already been created for this month.');
                    }
                }

                $request['locator_slip_no'] = null;
            }

            /* -------------------------------------------------------------------------- */
            /*                         Handle the rest of the data */
            /* -------------------------------------------------------------------------- */
            if ($empStatus == EmploymentStatus::CONTRACT_OF_SERVICE && $formType == LocatorFormType::FORM_C->value) {
                $request['period'] = $today->day <= 15 ? Period::FIRST_HALF : Period::SECOND_HALF;
            }

            $request['employee_id'] = $employee->id;
            $request['date'] = $today->toDateString();

            $ls = $this->model->create($request);

            return $ls;
        }, self::MAX_TRANSACTION_DEADLOCK_ATTEMPTS);
    }

    /** {@inheritDoc} */
    public function read(LocatorSlip|int $locatorSlip): LocatorSlip
    {
        // check if Locator or int
        if ($locatorSlip instanceof LocatorSlip) {
            $locatorSlip = $this->model->findOrFail($locatorSlip->id);
        } else {
            $locatorSlip = $this->model->findOrFail($locatorSlip);
        }

        return $locatorSlip;
    }

    public function viewEmployeeLocator(Employee $employee): LengthAwarePaginator
    {
        $query = $this->model->query()->where('employee_id', $employee->id)->orderBy('date', 'desc');

        return $this->buildPagination(PaginationType::LENGTH_AWARE, $query);

    }

    /** {@inheritDoc} */
    public function readGrouped(): LengthAwarePaginator
    {
        $query = Employee::withLocatorSlip()->with(['individualBasicDetail', 'office', 'division', 'sectionOrUnit']);

        return $this->buildPagination(PaginationType::LENGTH_AWARE, $query);
    }

    /**
     * {@inheritDoc}
     */
    public function update(LocatorSlip $locatorSlip, array $newLocatorSlipInfo): LocatorSlip
    {
        return DB::transaction(function () use ($locatorSlip, $newLocatorSlipInfo) {
            // Separate those with ids vs without to perform the correct operation.
            foreach ($newLocatorSlipInfo['locator_slip_logger'] as $logs) {
                if (isset($logs['id']) && $logs['id'] != null) {
                    $logRecord = LocatorSlipLogger::findOrFail($logs['id']);
                    $logRecord->update(Arr::except($logs, 'id'));
                } else {
                    $newLogs = $locatorSlip->locatorSlipLogger()->create($logs);

                    /* -------------------------------------------------------------------------- */
                    /*                             Update DTR Remarks */
                    /* -------------------------------------------------------------------------- */
                    // search for dtr == log date.
                    // if exists, append to remarks.
                    // else, create dtr and add remarks.
                    $dtr = DailyTimeRecord::where('date', $newLogs->date)->where('employee_id', $locatorSlip->employee_id)->first();
                    $lsMonth = Carbon::parse($newLogs->date)->format('Y-m');
                    $headlineApproval = Str::headline($newLogs->approved_for->value);
                    $lsNo = $locatorSlip->locator_slip_no ? "LS No.: $locatorSlip->locator_slip_no" : '';
                    $remarks = "$headlineApproval $lsNo - $newLogs->purpose at $newLogs->destination";

                    if ($dtr) {
                        $existingRemarks = $dtr->employee_remarks;
                        $payload = [
                            'month' => $lsMonth,
                            'dtr' => [
                                [
                                    'id' => $dtr->id,
                                    'employee_remarks' => $existingRemarks ? $existingRemarks."\n\n".$remarks : $remarks,
                                ],
                            ],
                        ];

                    } else {
                        $payload = [
                            'month' => $lsMonth,
                            'dtr' => [
                                [
                                    'date' => $newLogs->date,
                                    'employee_remarks' => $remarks,
                                ],
                            ],
                        ];
                    }

                    $this->dailyTimeRecordService->update($locatorSlip->employee, $payload);

                }
            }

            return $locatorSlip->fresh();
        }, self::MAX_TRANSACTION_DEADLOCK_ATTEMPTS);
    }
}
