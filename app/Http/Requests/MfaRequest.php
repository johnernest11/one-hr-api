<?php

namespace App\Http\Requests;

use App\Enums\AuthenticationType;
use Illuminate\Foundation\Http\FormRequest;

class MfaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function rules(): array
    {
        $routeName = $this->route()->getName();

        return match ($routeName) {
            'auth.mfa.verify-code' => $this->getVerifyCodeRules(),
            'auth.mfa.send-code' => $this->getSendCodeRules(),
            'auth.mfa.generate-qrcode' => $this->getGenerateQrcodeRules(),
            default => []
        };
    }

    private function getSendCodeRules(): array
    {
        return [
            'token' => ['required'],
        ];
    }

    private function getVerifyCodeRules(): array
    {
        return [
            'code' => ['required'],
            'token' => ['required'],
            'auth_type' => ['nullable', 'in:'.AuthenticationType::SANCTUM->value.','.AuthenticationType::JWT->value],
        ];
    }

    private function getGenerateQrcodeRules(): array
    {
        return [
            'token' => ['required'],
        ];
    }

    /**
     * Custom validation messages
     */
    public function messages(): array
    {
        return [
            'auth_type.in' => 'The :attribute field must either `jwt` or `sanctum`',
        ];
    }
}
