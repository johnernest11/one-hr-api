<?php

namespace App\Http\Requests\LocatorSlips;

use App\Enums\ApprovalType;
use App\Enums\LocatorFormType;
use App\Rules\DbTextMaxLength;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class LocatorSlipRequest extends FormRequest
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
            'locator-slips.store' => $this->getStoreLocatorSlipRule(),
            'locator-slips.update' => $this->getUpdateLocatorSlipRule(),
            default => [],
        };

    }

    public function getStoreLocatorSlipRule(): array
    {
        return [
            'form_type' => ['required', new Enum(LocatorFormType::class)],
        ];
    }

    public function getUpdateLocatorSlipRule(): array
    {
        return [
            'locator_slip_logger' => ['array'],
            'locator_slip_logger.*.id' => ['nullable', 'int'], // null if creating new records
            'locator_slip_logger.*.date' => ['required', 'date_format:Y-m-d'],
            'locator_slip_logger.*.time_out' => ['nullable', 'date_format:H:i'],
            'locator_slip_logger.*.time_in' => ['nullable', 'date_format:H:i'],
            'locator_slip_logger.*.destination' => ['required', 'string', new DbTextMaxLength],
            'locator_slip_logger.*.purpose' => ['required', 'string', new DbTextMaxLength],
            'locator_slip_logger.*.approved_for' => ['required',  new Enum(ApprovalType::class)],
            'locator_slip_logger.*.duration' => ['nullable',  'numeric'],
            'locator_slip_logger.*.remarks' => ['nullable', new DbTextMaxLength],
        ];
    }
}
