<?php

namespace App\Http\Requests;

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
            default => []
        };
    }

    private function getVerifyCodeRules(): array
    {
        return [
            'code' => ['required'],
            'token' => ['required'],
        ];
    }
}
