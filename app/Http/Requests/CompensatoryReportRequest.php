<?php

namespace App\Http\Requests;

use App\Enums\ARStatus;
use App\Enums\WeekNumber;
use App\Rules\DbTextMaxLength;
use App\Rules\DbVarcharMaxLength;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class CompensatoryReportRequest extends FormRequest
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
            'compensatory-report.store' => $this->getStoreUpdateCompensatoryReportRule(),
            default => [],
        };

    }

    /**
     * Compensatory Report Rules
     */
    public function getStoreUpdateCompensatoryReportRule(): array
    {
        $ar = $this->route('compensatoryReport'); // Get current CR

        return [
            'ctdo_period' => ['required', 'string', new DbVarcharMaxLength],
            'ctdo_supervisor_notes' => ['nullable', 'string', new DbVarcharMaxLength],
            'ctdo_status' => [new Enum(ARStatus::class)],
            'rows' => ['required', 'array'], // a row has to be present to be able to save
            'rows.*.id' => [
                'nullable',
                'exists:_c_t_d_o_rows,id',
                Rule::exists('_c_t_d_o_rows', 'id')->where(function ($query) use ($ar) {
                    $query->where('compensatory_report_id', $ar->id); // Validate and ensure that the ids match
                }),
                'int',
            ],
            'rows.*.days_of_the_week' => ['required_without:rows.*.id', new Enum(WeekNumber::class)], // 'week_num' has to be present in rows to be able to save AR
            'rows.*.work_date' => ['required_if:status,done', 'string', new DbVarcharMaxLength],
            'rows.*.time_start' => ['required_if:status,done', 'string', new DbTextMaxLength],
            'rows.*.time_end' => ['required_if:status,done', 'string', new DbTextMaxLength],
            'rows.*.accomplishment' => ['required_if:status,done', 'string', new DbTextMaxLength],
            'rows.*.authorized_claim' => ['required_if:status,done', 'string', new DbTextMaxLength],
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
