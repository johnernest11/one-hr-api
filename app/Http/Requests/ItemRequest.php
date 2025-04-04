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
        return [
            'number' => ['required', 'string', new DbVarcharMaxLength()],
            'date_of_creation' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday],
            'status' => ['required', new Enum(ItemStatus::class)],
            'date_filled_up' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday],
            'employment_status' => ['required', new Enum(EmploymentStatus::class)],
            'position_id' => ['required', 'integer', Rule::exists('positions', 'id')],
        ];
    }

    public function getSearchItemRule(): array
    {
        return [
            'query' => ['required', 'string'],
        ];
    }

    /**
     * Custom error messages
     */
    public function messages(): array
    {
        return [
            'position_id.exists' => 'The selected position ID does not exist.',
        ];
    }
}
