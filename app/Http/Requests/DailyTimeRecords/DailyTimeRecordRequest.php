<?php

namespace App\Http\Requests\DailyTimeRecords;

use App\Enums\DocumentStatus;
use App\Rules\BeforeOrEqualCurrentMonthYear;
use App\Rules\DbTextMaxLength;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class DailyTimeRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $routeName = $this->route()->getName();

        return match ($routeName) {
            'daily-time-records.store' => $this->getStoreDailyTimeRecordRule(),
            'daily-time-records.update' => $this->getUpdateDailyTimeRecordRule(),
            'daily-time-records.view-dtr' => $this->getViewDtrPerPeriodRangeRule(),
            'daily-time-records.search-time-logs' => $this->getSearchDailyTimeRecordRule(),
            'daily-time-records.generate-dtr' => $this->getGenerateDtrPerPeriodRangeRule(),
            default => [],
        };

    }

    public function getStoreDailyTimeRecordRule(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday],
            'ut' => ['nullable', 'double'],
            'is_edit_ut' => ['nullable', 'boolean'],
            'ot' => ['nullable', 'double'],
            'is_missing' => ['nullable', 'boolean'],
            'employee_remarks' => ['nullable', 'string', new DbTextMaxLength],
            'hr_remarks' => ['nullable', 'string', new DbTextMaxLength],
            'status' => ['required', new Enum(DocumentStatus::class)],
        ];

    }

    public function getUpdateDailyTimeRecordRule(): array
    {
        return [
            // Month is required if start_date and end_date is not present.
            // Start and end date is required if month is not present.
            // Either month and period range can be used to filter DTRs.
            'month' => ['nullable', 'required_without_all:start_date,end_date', 'date:Y-m', new BeforeOrEqualCurrentMonthYear],
            'start_date' => ['nullable', 'required_without:month', 'date:Y-m-d'],
            'end_date' => ['nullable', 'required_without:month', 'after_or_equal:start_date'],
            'status' => ['nullable', new Enum(DocumentStatus::class)],

            'dtr' => ['array'],
            'dtr.*.id' => ['nullable', 'int'],
            'dtr.*.ut' => ['nullable', 'numeric'],
            'dtr.*.is_edit_ut' => ['nullable', 'boolean'], // @todo add logic for this one later once UT and OT is implemented
            'dtr.*.ot' => ['nullable', 'double'],
            'dtr.*.employee_remarks' => ['nullable', 'string', new DbTextMaxLength],
            'dtr.*.hr_remarks' => ['nullable', 'string', new DbTextMaxLength],
            // Date will be required if ID is not passed (for new records).
            // Date will be excluded if ID is passed so as not to update the date on the given ID.
            'dtr.*.date' => ['nullable', 'required_without:dtr.*.id', 'exclude_with:dtr.*.id', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday],

            'dtr.*.time_logs' => ['array'],
            'dtr.*.time_logs.*.id' => ['nullable', 'int'],
            'dtr.*.time_logs.*.is_selected' => ['required', 'boolean'],
        ];

    }

    public function getViewDtrPerPeriodRangeRule(): array
    {
        return [
            // Month is required if start_date and end_date is not present.
            // Start and end date is required if month is not present.
            // Either month and period range can be used to filter DTRs.
            'month' => ['nullable', 'required_without_all:start_date,end_date', 'date:Y-m', new BeforeOrEqualCurrentMonthYear],
            'start_date' => ['nullable', 'required_without:month', 'date:Y-m-d'],
            'end_date' => ['nullable', 'required_without:month', 'after_or_equal:start_date'],
        ];

    }

    public function getSearchDailyTimeRecordRule(): array
    {
        return [
            'query' => ['required', 'string'],
            'limit' => ['nullable', 'int'],
            'is_my_profile' => ['required', 'boolean'], // Determines which view this is called.
        ];
    }

    public function getGenerateDtrPerPeriodRangeRule(): array
    {
        return [
            // Either month OR start_date+end_date is required
            'month' => ['nullable', 'required_without_all:start_date,end_date', 'date:Y-m', new BeforeOrEqualCurrentMonthYear],
            'start_date' => ['nullable', 'required_without:month', 'date:Y-m-d'],
            'end_date' => ['nullable', 'required_without:month', 'after_or_equal:start_date'],
            'sort' => ['nullable', 'in:asc,desc'], // Add sort validation
        ];
    }
}
