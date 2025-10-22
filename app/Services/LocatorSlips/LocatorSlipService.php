<?php

namespace App\Services\LocatorSlips;

use App\Enums\EmploymentStatus;
use App\Enums\LocatorFormType;
use App\Enums\PaginationType;
use App\Enums\Period;
use App\Models\ComprehensiveRecords\Employee;
use App\Models\LocatorSlip\LocatorSlip;
use App\Models\LocatorSlip\LocatorSlipLogger;
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
use Str;

class LocatorSlipService implements LocatorSlipManager
{
    use CanBuildPagination;

    public const MAX_TRANSACTION_DEADLOCK_ATTEMPTS = 5;

    private LocatorSlip $model;

    public function __construct(LocatorSlip $model)
    {
        $this->model = $model;
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
        $query = $query->filtered();

        return $this->buildPagination(PaginationType::LENGTH_AWARE, $query);

    }

    /** {@inheritDoc} */
    public function readGrouped(): LengthAwarePaginator
    {
        $query = Employee::query()->locatorSlipFiltered();

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
                }
            }

            return $locatorSlip->fresh();
        }, self::MAX_TRANSACTION_DEADLOCK_ATTEMPTS);
    }

    /**
     * {@inheritDoc}
     */
    public function search(
        Employee $employee,
        string $term,
        ?PaginationType $pagination = null
    ): Collection|Paginator|LengthAwarePaginator|CursorPaginator {
        /** @var Builder $locatorSlip */
        $query = $this->model->filtered();
        $ls = $query->where('locator_slip_no', 'like', "%$term%")
            ->where('employee_id', $employee->id);

        return $this->buildPagination($pagination, $ls);
    }

    /**
     * {@inheritDoc}
     */
    public function searchAll(
        string $term,
        ?PaginationType $pagination = null
    ): Collection|Paginator|LengthAwarePaginator|CursorPaginator {
        /** @var Builder $locatorSlip */
        $query = Employee::query()->locatorSlipFiltered();

        $updatedQuery = $query->where(function (Builder $q) use ($term) {
            $q->whereHas('individualBasicDetail', function ($sub) use ($term) {
                $sub->where('first_name', 'like', "%$term%")
                    ->orWhere('last_name', 'like', "%$term%")
                    ->orWhere('middle_name', 'like', "%$term%");
            })
                ->orWhereHas('locatorSlip', function ($sub) use ($term) {
                    $sub->where('locator_slip_no', 'like', "%$term%");
                });
        });

        $results = $this->buildPagination($pagination, $updatedQuery);

        if ($results instanceof Paginator || $results instanceof LengthAwarePaginator || $results instanceof CursorPaginator) {
            $collectionToTransform = $results->getCollection();
        } else {
            $collectionToTransform = $results;
        }

        $collectionToTransform->transform(function ($employee) use ($term) {
            // Check if the employee matches by name
            $matchesByName = str_contains(strtolower($employee->individualBasicDetail->first_name ?? ''), strtolower($term))
                || str_contains(strtolower($employee->individualBasicDetail->last_name ?? ''), strtolower($term))
                || str_contains(strtolower($employee->individualBasicDetail->middle_name ?? ''), strtolower($term));

            // If the match was by name => keep all locators
            if ($matchesByName) {
                return $employee;
            }

            // Otherwise, filter locator slips by locator number
            $employee->setRelation('locatorSlip', $employee->locatorSlip->filter(function ($slip) use ($term) {
                return str_contains(strtolower($slip->locator_slip_no ?? ''), strtolower($term));
            })->values());

            return $employee;
        });

        return $results;
    }

    /** {@inheritDoc} */
    public function checkActiveLog(Employee $employee): ?LocatorSlipLogger
    {

        $today = now()->toDateString();
        $lsl = LocatorSlipLogger::whereHas('locatorSlip.employee', function ($query) use ($employee) {
            $query->where('id', $employee->id);
        })
            ->whereDate('date', $today)
            ->where(function (Builder $query) {
                $query->whereNull('time_out')
                    ->orWhereNull('time_in');
            })
            ->first();

        return $lsl;

    }
}
