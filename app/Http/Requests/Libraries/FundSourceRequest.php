<?php

namespace App\Http\Requests\Libraries;

use Illuminate\Foundation\Http\FormRequest;

class FundSourceRequest extends FormRequest
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
            'fund-sources.search' => $this->getSearchFundSourcesRules(),
            default => [],
        };
    }

    /**
     * Fund Sources search rules
     */
    private function getSearchFundSourcesRules(): array
    {
        return [
            'limit' => ['nullable', 'int'],
            'query' => ['required', 'string'],
        ];
    }
}
