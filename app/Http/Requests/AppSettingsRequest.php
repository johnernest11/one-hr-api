<?php

namespace App\Http\Requests;

use App\Enums\AppTheme;
use App\Enums\MfaMethod;
use ConversionHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class AppSettingsRequest extends FormRequest
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
            'app-settings.store' => $this->getStoreSettingsRules(),
            default => [],
        };
    }

    /**
     * Get email availability rules
     */
    private function getStoreSettingsRules(): array
    {
        return [
            'theme' => [new Enum(AppTheme::class)],
            'mfa.enabled' => ['boolean'],
            'mfa.steps' => ['min:1', 'array'],
            'mfa.steps.*' => ['distinct', new Enum(MfaMethod::class)],
        ];
    }

    public function messages(): array
    {
        $mfaSteps = implode(', ', ConversionHelper::convertEnumToArray(MfaMethod::class));

        return [
            'mfa.steps.min' => 'The MFA steps must at least have one MFA method',
            'mfa.steps.*.Illuminate\Validation\Rules\Enum' => "Valid MFA Steps are: $mfaSteps",
        ];
    }
}
