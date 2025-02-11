<?php

namespace App\Http\Requests;

use App\Enums\ARStatus;
use App\Enums\WeekNumber;
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
            'accomplishment-report.store' => $this->getStoreAccomplishmentReportRule(),
            'accomplishment-report.update' => $this->getUpdateAccomplishmentReportRule(),
            default => [],
        };

    }

    /**
     * Accomplishment Report Rules
     */
    public function getStoreAccomplishmentReportRule(): array
    {
        return [
            'period' => ['nullable', 'string'],
            'supervisor_notes' => ['nullable', 'string'],
            'status' => [new Enum(ARStatus::class)],
            'rows' => ['required', 'array'], // a row has to be present to be able to save
            'rows.*.week_num' => ['required', new Enum(WeekNumber::class)], // 'week_num' has to be present in rows to be able to save AR
            'rows.*.dates_in_week' => ['nullable', 'string'],
            'rows.*.specific_activity' => ['nullable', 'string'],
            'rows.*.highlights' => ['nullable', 'string'],
        ];
    }

    public function getUpdateAccomplishmentReportRule(): array
    {
        return [
            'period' => ['nullable', 'string'],
            'supervisor_notes' => ['nullable', 'string'],
            'status' => [new Enum(ARStatus::class)],
            'rows' => ['nullable', 'array'],
            'rows.*.id' => ['nullable', 'int'],
            'rows.*.week_num' => ['nullable', new Enum(WeekNumber::class)],
            'rows.*.dates_in_week' => ['nullable', 'string'],
            'rows.*.specific_activity' => ['nullable', 'string'],
            'rows.*.highlights' => ['nullable', 'string'],
        ];
    }
}
