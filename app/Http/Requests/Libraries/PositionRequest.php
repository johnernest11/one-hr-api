<?php

namespace App\Http\Requests\Libraries;

use Illuminate\Foundation\Http\FormRequest;

class PositionRequest extends FormRequest
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
            'positions.index' => $this->getFetchPositionsRules(),
            'positions.search' => $this->getSearchPositionsRules(),
            default => [],
        };
    }

    /**
     * Fetch rules
     */
    private function getFetchPositionsRules(): array
    {
        return [
            'level' => ['integer', 'nullable', 'min:1', 'max:3'],
        ];
    }

    /**
     * Positions search rules
     */
    private function getSearchPositionsRules(): array
    {
        return [
            'per-page' => ['nullable', 'int'],
            'query' => ['required', 'string'],
        ];
    }

    /**
     * Custom message for validation
     */
    public function messages(): array
    {
        return [
            'level' => 'Valid values for the :attribute field are 1-3.',
        ];
    }
}
