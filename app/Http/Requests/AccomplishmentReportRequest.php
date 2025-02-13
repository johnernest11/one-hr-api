<?php

namespace App\Http\Requests;

use App\Enums\ARStatus;
use App\Enums\WeekNumber;
use App\Rules\DbTextMaxLength;
use App\Rules\DbVarcharMaxLength;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class AccomplishmentReportRequest extends FormRequest
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
            'accomplishment-report.store' => $this->getStoreUpdateAccomplishmentReportRule(),
            'accomplishment-report.update' => $this->getStoreUpdateAccomplishmentReportRule(),
            default => [],
        };

    }

    /**
     * Accomplishment Report Rules
     */
    public function getStoreUpdateAccomplishmentReportRule(): array
    {
        return [
            'period' => ['required', 'string', new DbVarcharMaxLength()],
            'supervisor_notes' => ['nullable', 'string', new DbVarcharMaxLength()],
            'status' => [new Enum(ARStatus::class)],
            'rows' => ['required', 'array'], // a row has to be present to be able to save
            'rows.*.id' => ['nullable', 'int'], // a row has to be present to be able to save
            'rows.*.week_num' => ['required', new Enum(WeekNumber::class)], // 'week_num' has to be present in rows to be able to save AR
            'rows.*.dates_in_week' => ['nullable', 'string', new DbVarcharMaxLength()],
            'rows.*.specific_activity' => ['nullable', 'string', new DbTextMaxLength()],
            'rows.*.highlights' => ['nullable', 'string', new DbTextMaxLength()],
        ];
    }
}
