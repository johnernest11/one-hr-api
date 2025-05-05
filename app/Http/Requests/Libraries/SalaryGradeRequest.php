<?php

namespace App\Http\Requests\Libraries;

use Illuminate\Foundation\Http\FormRequest;

class SalaryGradeRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $routeName = $this->route()->getName();

        return match ($routeName) {
            'salary-grades.index' => $this->getFetchSalaryGradesRules(),
            'salary-grades.search' => $this->getSearchSalaryGradesRules(),
            default => [],
        };
    }

    /**
     * Fetch rules
     */
    private function getFetchSalaryGradesRules(): array
    {
        return [
            'nbc-no' => ['integer', 'nullable'],
            'effective-date' => ['string', 'nullable'],
            'tranche' => ['integer', 'nullable'],
            'sg' => ['integer', 'nullable', 'min:1', 'max:33'],
            'step' => ['integer', 'nullable', 'min:1', 'max:8'],
            'active' => ['boolean', 'nullable'],
        ];
    }

    /**
     * Salary Grades search rules
     */
    private function getSearchSalaryGradesRules(): array
    {
        return [
            'limit' => ['nullable', 'int'],
            'query' => ['required', 'string'],
        ];
    }
}
