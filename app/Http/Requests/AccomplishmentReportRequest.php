<?php

namespace App\Http\Requests;

use App\Enums\ARStatus;
use App\Enums\WeekNumber;
use App\Rules\DbTextMaxLength;
use App\Rules\DbVarcharMaxLength;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'accomplishment-report.search' => $this->getSearchAccomplishmentReportRule(),
            default => [],
        };

    }

    /**
     * Accomplishment Report Store Rules
     */
    public function getStoreAccomplishmentReportRule(): array
    {
        $ar = $this->route('accomplishmentReport'); // Get current AR

        return [
            'period' => ['required', 'string', new DbVarcharMaxLength],
            'supervisor_notes' => ['nullable', 'string', new DbVarcharMaxLength],
            'status' => [new Enum(ARStatus::class)],
            'rows' => ['required', 'array'], // a row has to be present to be able to save
            'rows.*.id' => ['nullable', 'exists:a_r_rows,id',
                Rule::exists('a_r_rows', 'id')->where(function ($query) use ($ar) {
                    $query->where('accomplishment_report_id', $ar->id); // Validate and ensure that the ids match
                }),
                'int'],
            'rows.*.week_num' => ['required_without:rows.*.id', new Enum(WeekNumber::class)], // 'week_num' has to be present in rows to be able to save AR
            'rows.*.dates_in_week' => ['required_if:status,done', 'string', new DbVarcharMaxLength],
            'rows.*.specific_activity' => ['required_if:status,done', 'string', new DbTextMaxLength],
            'rows.*.highlights' => ['required_if:status,done', 'string', new DbTextMaxLength],
        ];
    }

    /**
     * Accomplishment Report Update Rules
     */
    public function getUpdateAccomplishmentReportRule(): array
    {
        $ar = $this->route('accomplishmentReport'); // Get current AR

        return [
            'period' => ['required', 'string', new DbVarcharMaxLength],
            'supervisor_notes' => ['nullable', 'string', new DbVarcharMaxLength],
            'status' => [new Enum(ARStatus::class)],
            'rows' => ['required', 'array',
                function ($attribute, $value, $fail) {
                    $nonDeletedRows = collect($value)->reject(fn ($row) => ! empty($row['_delete']));
                    if ($nonDeletedRows->isEmpty()) {
                        $fail('An accomplishment report must have at least one active row.');
                    }
                },
            ], // a row has to be present to be able to save
            'rows.*.id' => ['nullable', 'exists:a_r_rows,id',
                Rule::exists('a_r_rows', 'id')->where(function ($query) use ($ar) {
                    $query->where('accomplishment_report_id', $ar->id); // Validate and ensure that the ids match
                }),
                'int'],
            'rows.*._delete' => ['nullable', 'boolean'],
            'rows.*.week_num' => ['exclude_if:rows.*._delete,true', 'required_without:rows.*.id', new Enum(WeekNumber::class)],
            'rows.*.dates_in_week' => ['exclude_if:rows.*._delete,true', 'required_if:status,done', 'string', new DbVarcharMaxLength],
            'rows.*.specific_activity' => ['exclude_if:rows.*._delete,true', 'required_if:status,done', 'string', new DbTextMaxLength],
            'rows.*.highlights' => ['exclude_if:rows.*._delete,true', 'required_if:status,done', 'string', new DbTextMaxLength],
        ];
    }

    public function getSearchAccomplishmentReportRule(): array
    {
        return [
            'query' => ['required', 'string'],
            'limit' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Custom error messages
     */
    public function messages(): array
    {
        return [
            'rows.*.id.exists' => 'The :attribute does not belong to the accomplishment report.',
        ];
    }
}
