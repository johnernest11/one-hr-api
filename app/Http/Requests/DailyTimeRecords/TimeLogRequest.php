<?php

namespace App\Http\Requests\DailyTimeRecords;

use Illuminate\Foundation\Http\FormRequest;

class TimeLogRequest extends FormRequest
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
            'time-logs.log-time' => $this->getLogTimeRule(),
            default => [],
        };

    }

    public function getLogTimeRule(): array
    {
        return [
            'scanned_qr' => ['required', 'string'],
            'captured_image' => ['nullable', 'file', 'image', 'max:5120'],
        ];

    }
}
