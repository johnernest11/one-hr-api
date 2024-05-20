<?php

namespace App\Http\Requests;

use App\Rules\MfaStep;
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
            'auth.mfa.verify-backup-code' => $this->getVerifyBackupCodeRules(),
            'auth.mfa.un-enroll-user' => $this->getUnEnrollUserRules(),
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
        ];
    }

    private function getGenerateQrcodeRules(): array
    {
        return [
            'token' => ['required'],
        ];
    }

    private function getVerifyBackupCodeRules(): array
    {
        return [
            'token' => ['required'],
            'code' => ['required'],
        ];
    }

    private function getUnEnrollUserRules(): array
    {
        return [
            'mfa_step' => ['required', new MfaStep()],
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
