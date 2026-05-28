<?php

namespace App\Http\Requests;

use App\Enums\EmploymentStatus;
use App\Enums\ItemStatus;
use App\Rules\DbVarcharMaxLength;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ItemRequest extends FormRequest
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
            'item.store' => $this->getStoreUpdateItemRule(),
            'item.update' => $this->getStoreUpdateItemRule(),
            'item.search' => $this->getSearchItemRule(),
            default => [],
        };

    }

    /**
     * Item Rules
     */
    public function getStoreUpdateItemRule(): array
    {
        $item = '';
        if ($this->method() == 'PUT') {
            $item = $this->route('item');
        }

        return [
            // Organization Data
            'division_id' => ['required', 'integer', Rule::exists('divisions', 'id')],
            'section_or_unit_id' => ['required', 'integer', Rule::exists('section_or_units', 'id')],
            'program_id' => ['nullable', 'integer', Rule::exists('programs', 'id')],
            'office_id' => ['required', 'integer', Rule::exists('offices', 'id')],
            'psipop_id' => ['nullable', 'integer', Rule::exists('divisions', 'id')],

            // Compensation & Employment Details
            'employment_status' => ['required', new Enum(EmploymentStatus::class)],
            'fund_source_id' => ['required', 'integer', Rule::exists('fund_sources', 'id')],
            'salary_grade_id' => ['required', 'integer', Rule::exists('salary_grades', 'id')],

            // Position Details
            'position_id' => ['required', 'integer', Rule::exists('positions', 'id')],
            'item_classification' => ['nullable', 'string', new DbVarcharMaxLength],
            'number' => [
                Rule::requiredIf($this->isMethod('POST')),
                'string',
                new DbVarcharMaxLength,
                Rule::unique('items', 'number')->ignore($item),
            ],
            'date_of_creation' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday],

            // Designation and Assignment Details
            'designation' => ['nullable', 'string', new DbVarcharMaxLength],
            'date_of_designation' => ['nullable', 'date_format:Y-m-d'],
            'special_order_number' => ['nullable', 'string', new DbVarcharMaxLength],

            // Position History and Vacancy Tracking
            'status' => ['required', new Enum(ItemStatus::class)],
            'mode_of_accession' => ['nullable', 'string', new DbVarcharMaxLength],
            'date_filled_up' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday],
            'history_of_position' => ['nullable', 'string'],
            'former_incumbent' => ['nullable', 'string', new DbVarcharMaxLength],
            'mode_of_separation' => ['nullable', 'string', new DbVarcharMaxLength],
            'date_of_vacant' => ['nullable', 'date_format:Y-m-d'],
            'remarks_of_vacancy' => ['nullable', 'string'],
            'status_of_vacant_position' => ['nullable', 'string', new DbVarcharMaxLength],
            'direct_contact_exposure_with_client' => ['nullable', 'string', new DbVarcharMaxLength],
            'remarks' => ['nullable', 'string'],
        ];
    }

    public function getSearchItemRule(): array
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
            'position_id.exists' => 'The selected position ID does not exist.',
            'fund_source_id.exists' => 'The selected fund source ID does not exist.',
        ];
    }
}
