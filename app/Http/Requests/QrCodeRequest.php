<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QrCodeRequest extends FormRequest
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
            'qr-codes.verify-qr' => $this->getVerifyQrRule(),
            'qr-codes.update' => $this->getUpdateQrRule(),
            default => [],
        };

    }

    public function getVerifyQrRule(): array
    {
        return [
            'scanned_qr' => ['required', 'string'],
        ];

    }

    public function getUpdateQrRule(): array
    {
        return [
            'is_active' => ['required', 'boolean'], // Can only update the active status of the QR.
        ];

    }
}
