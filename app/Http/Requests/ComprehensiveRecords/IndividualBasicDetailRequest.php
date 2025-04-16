<?php

namespace App\Http\Requests\ComprehensiveRecords;

use App\Enums\BloodType;
use App\Enums\Citizenship;
use App\Enums\CitizenshipAcquisition;
use App\Enums\CivilStatus;
use App\Enums\SexualCategory;
use App\Rules\DbTextMaxLength;
use App\Rules\DbVarcharMaxLength;
use App\Rules\InternationalPhoneNumberFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Propaganistas\LaravelPhone\Rules\Phone as PhoneRule;

class IndividualBasicDetailRequest extends FormRequest
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
            'individual.store' => $this->getStoreIndividualRules(),
            'individual.update' => $this->getUpdateIndividualRules(),
            default => [],
        };
    }

    public function getStoreIndividualRules(): array
    {
        return [
            // Group the request as array
            // IndividualBasicDetail
            'individual' => ['array'],
            'individual.first_name' => ['string', 'required', new DbVarcharMaxLength()],
            'individual.last_name' => ['string', 'required', new DbVarcharMaxLength()],
            'individual.middle_name' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual.ext_name' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual.birthday' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday], // This is Date of Birth in the frontend
            'individual.sex' => ['required', new Enum(SexualCategory::class)],

            'individual.place_of_birth' => ['required', 'string', new DbVarcharMaxLength()],
            'individual.civil_status' => ['required', new Enum(CivilStatus::class)],
            'individual.height' => ['required', 'numeric'],
            'individual.weight' => ['required', 'numeric'],
            'individual.blood_type' => ['required', new Enum(BloodType::class)],
            'individual.gsis_no' => ['string', 'nullable', new DbTextMaxLength()],
            'individual.pag_ibig_no' => ['string', 'required', new DbTextMaxLength()],
            'individual.philhealth_no' => ['string', 'required', new DbTextMaxLength()],
            'individual.sss_no' => ['string', 'required', new DbTextMaxLength()],
            'individual.tin' => ['string', 'required', new DbTextMaxLength()],
            'individual.citizenship' => ['string', 'required', new Enum(Citizenship::class)],
            'individual.citizenship_acquisition' => ['string', 'nullable', new Enum(CitizenshipAcquisition::class)],

            // Employee
            'employee' => ['array'],
            'employee.*.id_number' => ['string', 'nullable', new dbvarcharmaxlength()],
            'employee.*.item_id' => ['int', 'required'],
            'employee.*.agency_employee_no' => ['string', 'nullable', new DbTextMaxLength()],

            // PDS models
            // IndividualAddress
            'individualAddress' => ['array'],
            'individualAddress.*.residential_house_block_lot_no' => ['string', 'nullable', new DbTextMaxLength()],
            'individualAddress.*.residential_street' => ['string', 'nullable', new DbTextMaxLength()],
            'individualAddress.*.residential_subdivision_village' => ['string', 'nullable', new DbTextMaxLength()],
            'individualAddress.*.residential_brgy_id' => ['required', 'exists:barangays,id'],
            'individualAddress.*.residential_citymun_id' => ['required', 'exists:cities,id'],
            'individualAddress.*.residential_province_id' => ['required', 'exists:provinces,id'],
            'individualAddress.*.residential_region_id' => ['required', 'exists:regions,id'],
            'individualAddress.*.residential_zip_code' => ['required', 'digits:4'],
            'individualAddress.*.permanent_house_block_lot_no' => ['string', 'nullable', new DbTextMaxLength()],
            'individualAddress.*.permanent_street' => ['string', 'nullable', new DbTextMaxLength()],
            'individualAddress.*.permanent_subdivision_village' => ['string', 'nullable', new DbTextMaxLength()],
            'individualAddress.*.permanent_brgy_id' => ['required', 'exists:barangays,id'],
            'individualAddress.*.permanent_citymun_id' => ['required', 'exists:cities,id'],
            'individualAddress.*.permanent_province_id' => ['required', 'exists:provinces,id'],
            'individualAddress.*.permanent_region_id' => ['required', 'exists:regions,id'],
            'individualAddress.*.permanent_zip_code' => ['required', 'digits:4'],

            // IndividualContactInfo
            'individualContactInfo' => ['array'],
            'individualContactInfo.*.mobile_no' => [
                'required',
                'unique:user_profiles,mobile_number,'.auth()->id().',user_id',
                new InternationalPhoneNumberFormat(),
                (new PhoneRule())->country('PH')->mobile(),

            ],
            'individualContactInfo.*.tel_no' => [
                'nullable',
                new InternationalPhoneNumberFormat(),
                (new PhoneRule())->country('PH')->fixedLine(),
            ],
            'individualContactInfo.*.email_address' => ['required', 'email', 'unique:individual_contact_infos,email_address,'.auth()->id()],
        ];
    }

    public function getUpdateIndividualRules(): array
    {
        $individualBasicDetails = $this->route('individualBasicDetail'); // Get current individual

        return [
            // Group the request as array
            // IndividualBasicDetail
            'individual' => ['array'],
            'individual.first_name' => ['string', 'required', new DbVarcharMaxLength()],
            'individual.last_name' => ['string', 'required', new DbVarcharMaxLength()],
            'individual.middle_name' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual.ext_name' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual.sex' => ['required', new Enum(SexualCategory::class)],
            'individual.birthday' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday], // This is Date of Birth in the frontend

            'individual.place_of_birth' => ['required', 'string', new DbVarcharMaxLength()],
            'individual.civil_status' => ['required', new Enum(CivilStatus::class)],
            'individual.height' => ['required', 'numeric'],
            'individual.weight' => ['required', 'numeric'],
            'individual.blood_type' => ['required', new Enum(BloodType::class)],
            'individual.gsis_no' => ['string', 'nullable', new DbTextMaxLength()],
            'individual.pag_ibig_no' => ['string', 'required', new DbTextMaxLength()],
            'individual.philhealth_no' => ['string', 'required', new DbTextMaxLength()],
            'individual.sss_no' => ['string', 'required', new DbTextMaxLength()],
            'individual.tin' => ['string', 'required', new DbTextMaxLength()],
            'individual.citizenship' => ['string', 'required', new Enum(Citizenship::class)],
            'individual.citizenship_acquisition' => ['string', 'nullable', new Enum(CitizenshipAcquisition::class)],

            // Employee
            'employee' => ['array'],
            'employee.*.id' => ['nullable', 'exists:employees,id', 'int',
                Rule::exists('employees', 'id')->where(function ($query) use ($individualBasicDetails) {
                    $query->where('individual_basic_detail_id', $individualBasicDetails->id); // Validate and ensure that the ids match
                }),
            ],
            'employee.*.id_number' => ['string', 'nullable', new dbvarcharmaxlength()],
            'employee.*.item_id' => ['int', 'required'],
            'employee.*.agency_employee_no' => ['string', 'nullable', new DbTextMaxLength()],

            // PDS models
            // IndividualAddress
            'individualAddress' => ['array'],
            'individualAddress.*.id' => ['nullable', 'exists:individual_addresses,id', 'int',
                Rule::exists('individual_addresses', 'id')->where(function ($query) use ($individualBasicDetails) {
                    $query->where('individual_basic_detail_id', $individualBasicDetails->id); // Validate and ensure that the ids match
                }),
            ],
            'individualAddress.*.residential_house_block_lot_no' => ['string', 'nullable', new DbTextMaxLength()],
            'individualAddress.*.residential_street' => ['string', 'nullable', new DbTextMaxLength()],
            'individualAddress.*.residential_subdivision_village' => ['string', 'nullable', new DbTextMaxLength()],
            'individualAddress.*.residential_brgy_id' => ['required', 'exists:barangays,id'],
            'individualAddress.*.residential_citymun_id' => ['required', 'exists:cities,id'],
            'individualAddress.*.residential_province_id' => ['required', 'exists:provinces,id'],
            'individualAddress.*.residential_region_id' => ['required', 'exists:regions,id'],
            'individualAddress.*.residential_zip_code' => ['required', 'digits:4'],
            'individualAddress.*.permanent_house_block_lot_no' => ['string', 'nullable', new DbTextMaxLength()],
            'individualAddress.*.permanent_street' => ['string', 'nullable', new DbTextMaxLength()],
            'individualAddress.*.permanent_subdivision_village' => ['string', 'nullable', new DbTextMaxLength()],
            'individualAddress.*.permanent_brgy_id' => ['required', 'exists:barangays,id'],
            'individualAddress.*.permanent_citymun_id' => ['required', 'exists:cities,id'],
            'individualAddress.*.permanent_province_id' => ['required', 'exists:provinces,id'],
            'individualAddress.*.permanent_region_id' => ['required', 'exists:regions,id'],
            'individualAddress.*.permanent_zip_code' => ['required', 'digits:4'],

            // IndividualContactInfo
            'individualContactInfo' => ['array'],
            'individualContactInfo.*.id' => ['nullable', 'exists:individual_contact_infos,id', 'int',
                Rule::exists('individual_contact_infos', 'id')->where(function ($query) use ($individualBasicDetails) {
                    $query->where('individual_basic_detail_id', $individualBasicDetails->id); // Validate and ensure that the ids match
                }),
            ],
            'individualContactInfo.*.mobile_no' => [
                'required',
                'unique:user_profiles,mobile_number,'.auth()->id().',user_id',
                new InternationalPhoneNumberFormat(),
                (new PhoneRule())->country('PH')->mobile(),

            ],
            'individualContactInfo.*.tel_no' => [
                'nullable',
                new InternationalPhoneNumberFormat(),
                (new PhoneRule())->country('PH')->fixedLine(),
            ],
            'individualContactInfo.*.email_address' => ['required', 'email',
                Rule::unique('individual_contact_infos', 'email_address')
                    ->ignore($individualBasicDetails ? $individualBasicDetails->individualContactInfo->email_address : null, 'email_address'),
            ],
            'individualContactInfo.*._delete' => ['nullable', 'boolean'], // @todo Test model deletion; Remove later
        ];
    }

    public function messages(): array
    {
        return [
            '*.*.id.exists' => 'The :attribute does not belong to the individual you are trying to update.',
        ];
    }
}
