<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PermissionRequest extends FormRequest
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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $routeName = $this->route()->getName();

        return match ($routeName) {
            'permissions.index' => $this->getFetchPermissionRules(),
            default => [],
        };
    }

    /**
     * Fetch permissions rules
     */
    private function getFetchPermissionRules(): array
    {
        return [
            'type' => ['string', 'nullable', 'in:users,api_keys,all'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'The :attribute filed should either be `users`, `api_keys`, or `all`',
        ];
    }
}
