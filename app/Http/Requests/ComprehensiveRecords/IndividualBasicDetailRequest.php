<?php

namespace App\Http\Requests\ComprehensiveRecords;

use App\Enums\AcademicLevel;
use App\Enums\BloodType;
use App\Enums\Citizenship;
use App\Enums\CitizenshipAcquisition;
use App\Enums\CivilStatus;
use App\Enums\ExtensionNameCategory;
use App\Enums\FamilyMemberCategory;
use App\Enums\PDSFormType;
use App\Enums\Role;
use App\Enums\SexualCategory;
use App\Models\User;
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
            'individual.viewAllIndividuals' => $this->getViewIndividualRules(),
            'individual.store' => $this->getStoreIndividualRules(),
            'individual.update' => $this->getUpdateIndividualRules(),
            'individual.search' => $this->getSearchIndividualRules(),
            default => [],
        };
    }

    public function getViewIndividualRules(): array
    {
        return [
            'limit' => ['nullable', 'int'],
        ];
    }

    public function getStoreIndividualRules(): array
    {
        return [
            // IndividualBasicDetail
            'individual' => ['array'],
            'individual.first_name' => ['required', 'string', new DbVarcharMaxLength()],
            'individual.last_name' => ['required', 'string', new DbVarcharMaxLength()],
            'individual.sex' => ['required', new Enum(SexualCategory::class)],
            'individual.birthday' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday],
            'individual.place_of_birth' => ['required', 'string', new DbVarcharMaxLength()],
            'individual.civil_status' => ['required', new Enum(CivilStatus::class)],
            'individual.height' => ['required', 'numeric'],
            'individual.weight' => ['required', 'numeric'],
            'individual.blood_type' => ['required', new Enum(BloodType::class)],
            'individual.pag_ibig_no' => ['required', 'string', new DbTextMaxLength()],
            'individual.philhealth_no' => ['required', 'string', new DbTextMaxLength()],
            'individual.sss_no' => ['required', 'string', new DbTextMaxLength()],
            'individual.tin' => ['required', 'string', new DbTextMaxLength()],
            'individual.citizenship' => ['required', 'string', new Enum(Citizenship::class)],
            'individual.middle_name' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual.ext_name' => ['nullable', 'string', new Enum(ExtensionNameCategory::class)],
            'individual.citizenship_acquisition' => ['nullable', 'string', new Enum(CitizenshipAcquisition::class)],
            'individual.gsis_no' => ['nullable', 'string', new DbTextMaxLength()],

            // Employee
            'employee' => ['array', $this->requiredIfUserIsPPMSAdmin()],
            'employee.item_id' => ['nullable', 'int', $this->requiredIfUserIsPPMSAdmin()],
            'employee.salary_grade_id' => ['nullable', 'int', $this->requiredIfUserIsPPMSAdmin()],
            'employee.program_id' => ['nullable', 'int'],
            'employee.office_id' => ['nullable', 'int', $this->requiredIfUserIsPPMSAdmin()],
            'employee.division_id' => ['nullable', 'int', $this->requiredIfUserIsPPMSAdmin()],
            'employee.section_or_unit_id' => ['nullable', 'int', $this->requiredIfUserIsPPMSAdmin()],
            'employee.id_number' => ['nullable', 'string', new DbVarcharMaxLength()],
            'employee.agency_employee_no' => ['nullable', 'string', new DbTextMaxLength()],

            // IndividualAddress
            'individual_address' => ['array'],
            'individual_address.*.residential_brgy_id' => ['required', 'exists:barangays,id'],
            'individual_address.*.residential_citymun_id' => ['required', 'exists:cities,id'],
            'individual_address.*.residential_province_id' => ['required', 'exists:provinces,id'],
            'individual_address.*.residential_region_id' => ['required', 'exists:regions,id'],
            'individual_address.*.residential_zip_code' => ['required', 'digits:4'],
            'individual_address.*.permanent_brgy_id' => ['required', 'exists:barangays,id'],
            'individual_address.*.permanent_citymun_id' => ['required', 'exists:cities,id'],
            'individual_address.*.permanent_province_id' => ['required', 'exists:provinces,id'],
            'individual_address.*.permanent_region_id' => ['required', 'exists:regions,id'],
            'individual_address.*.permanent_zip_code' => ['required', 'digits:4'],
            'individual_address.*.residential_house_block_lot_no' => ['nullable', 'string', new DbTextMaxLength()],
            'individual_address.*.residential_street' => ['nullable', 'string', new DbTextMaxLength()],
            'individual_address.*.residential_subdivision_village' => ['nullable', 'string', new DbTextMaxLength()],
            'individual_address.*.permanent_house_block_lot_no' => ['nullable', 'string', new DbTextMaxLength()],
            'individual_address.*.permanent_street' => ['nullable', 'string', new DbTextMaxLength()],
            'individual_address.*.permanent_subdivision_village' => ['nullable', 'string', new DbTextMaxLength()],

            // IndividualContactInfo
            'individual_contact_info' => ['array'],
            'individual_contact_info.*.mobile_no' => [
                'required',
                Rule::unique('user_profiles', 'mobile_number')->ignore(auth()->id(), 'user_id'),
                new InternationalPhoneNumberFormat(),
                (new PhoneRule())->country('PH')->mobile(),
            ],
            'individual_contact_info.*.email_address' => ['nullable', 'email', 'unique:individual_contact_infos,email_address,'.request('individual_contact_info.0.id')], // Get the first record on the array since this is a has one relationship anyway. Ignore uniqueness when id is given.
            'individual_contact_info.*.tel_no' => [
                'nullable',
                new InternationalPhoneNumberFormat(),
                (new PhoneRule())->country('PH')->fixedLine(),
            ],

            // IndividualFamily
            'individual_family' => ['array'],
            'individual_family.*.middle_name' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_family.*.ext_name' => ['nullable', new Enum(ExtensionNameCategory::class)],
            'individual_family.*.occupation' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_family.*.employers_business_name' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_family.*.business_address' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_family.*.telephone_no' => [
                'nullable',
                new InternationalPhoneNumberFormat(),
                (new PhoneRule())->country('PH')->mobile(),
            ],
            'individual_family.*._delete' => ['nullable', 'boolean'], // Can delete family members.
            'individual_family.*.first_name' => ['string', 'required', new DbVarcharMaxLength()],
            'individual_family.*.last_name' => ['string', 'required', new DbVarcharMaxLength()],
            'individual_family.*.class' => ['required', new Enum(FamilyMemberCategory::class)],
            'individual_family.*.date_of_birth' => ['required_if:individual_family.*.class,Children', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday],

            // IndividualEducationalBackground
            'individual_educational_background' => ['array'],
            'individual_educational_background.*.level' => ['required', new Enum(AcademicLevel::class)],
            'individual_educational_background.*.schools_name' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_educational_background.*.education_description' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_educational_background.*.period_of_attendance_from' => ['date_format:Y', 'before_or_equal:'.$this->dateToday, 'nullable', new DbVarcharMaxLength()],
            'individual_educational_background.*.period_of_attendance_to' => ['date_format:Y', 'before_or_equal:'.$this->dateToday, 'nullable', new DbVarcharMaxLength()],
            'individual_educational_background.*.highest_level_units_earned' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_educational_background.*.year_graduated' => ['date_format:Y', 'before_or_equal:'.$this->dateToday, 'nullable', new DbVarcharMaxLength()],
            'individual_educational_background.*.scholarship_academic_honors_received' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_educational_background.*._delete' => ['nullable', 'boolean'], // Can delete educational backgrounds.

            // -----> C2 starts here <-----
            // IndividualEligibilities
            'individual_eligibility' => ['array'],
            'individual_eligibility.*.eligibility' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_eligibility.*.rating' => ['numeric', 'nullable', new DbVarcharMaxLength()],
            'individual_eligibility.*.date_of_examination_conferment' => ['date_format:Y-m-d', 'before_or_equal:'.$this->dateToday, 'nullable', new DbVarcharMaxLength()],
            'individual_eligibility.*.place_of_examination' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_eligibility.*.license_number' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_eligibility.*.license_date_of_validity' => ['date_format:Y-m-d', 'nullable', new DbVarcharMaxLength()],
        ];
    }

    public function getUpdateIndividualRules(): array
    {
        // Get which form will be updated.
        // Will not add the form_type to the rules since we only need it here to distinguish which form will be updated.
        $form_type = $this->input('form_type');

        // Check which form will be updated and return the appropriate rules.
        $rules = match ($form_type) {
            PDSFormType::C1->value => $this->getC1Rules(),
            PDSFormType::C2->value => $this->getC2Rules(),
        };

        return $rules;
    }

    public function getC1Rules(): array
    {
        $individualBasicDetail = $this->route('individualBasicDetail');
        $individualId = $individualBasicDetail ? $individualBasicDetail->id : null;

        return [
            // -----> C1 starts here <-----
            // IndividualBasicDetail
            'individual' => ['array'],
            'individual.first_name' => ['required', 'string', new DbVarcharMaxLength()],
            'individual.last_name' => ['required', 'string', new DbVarcharMaxLength()],
            'individual.sex' => ['required', new Enum(SexualCategory::class)],
            'individual.birthday' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday],
            'individual.place_of_birth' => ['required', 'string', new DbVarcharMaxLength()],
            'individual.civil_status' => ['required', new Enum(CivilStatus::class)],
            'individual.height' => ['required', 'numeric'],
            'individual.weight' => ['required', 'numeric'],
            'individual.blood_type' => ['required', new Enum(BloodType::class)],
            'individual.pag_ibig_no' => ['required', 'string', new DbTextMaxLength()],
            'individual.philhealth_no' => ['required', 'string', new DbTextMaxLength()],
            'individual.sss_no' => ['required', 'string', new DbTextMaxLength()],
            'individual.tin' => ['required', 'string', new DbTextMaxLength()],
            'individual.citizenship' => ['required', 'string', new Enum(Citizenship::class)],
            'individual.middle_name' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual.ext_name' => ['nullable', 'string', new Enum(ExtensionNameCategory::class)],
            'individual.citizenship_acquisition' => ['nullable', 'string', new Enum(CitizenshipAcquisition::class)],
            'individual.gsis_no' => ['nullable', 'string', new DbTextMaxLength()],

            // Employee
            'employee' => ['array', $this->requiredIfUserIsPPMSAdmin()],
            'employee.item_id' => ['nullable', 'int', $this->requiredIfUserIsPPMSAdmin()],
            'employee.salary_grade_id' => ['nullable', 'int', $this->requiredIfUserIsPPMSAdmin()],
            'employee.program_id' => ['nullable', 'int'],
            'employee.office_id' => ['nullable', 'int', $this->requiredIfUserIsPPMSAdmin()],
            'employee.division_id' => ['nullable', 'int', $this->requiredIfUserIsPPMSAdmin()],
            'employee.section_or_unit_id' => ['nullable', 'int', $this->requiredIfUserIsPPMSAdmin()],
            'employee.id' => [
                'nullable',
                'int',
                Rule::exists('employees', 'id')->where(function ($query) use ($individualId) {
                    if ($individualId) {
                        $query->where('individual_basic_detail_id', $individualId);
                    }
                }),
            ],
            'employee.id_number' => ['nullable', 'string', new DbVarcharMaxLength()],
            'employee.agency_employee_no' => ['nullable', 'string', new DbTextMaxLength()],

            // IndividualAddress
            'individual_address' => ['array'],
            'individual_address.*.residential_brgy_id' => ['required', 'exists:barangays,id'],
            'individual_address.*.residential_citymun_id' => ['required', 'exists:cities,id'],
            'individual_address.*.residential_province_id' => ['required', 'exists:provinces,id'],
            'individual_address.*.residential_region_id' => ['required', 'exists:regions,id'],
            'individual_address.*.residential_zip_code' => ['required', 'digits:4'],
            'individual_address.*.permanent_brgy_id' => ['required', 'exists:barangays,id'],
            'individual_address.*.permanent_citymun_id' => ['required', 'exists:cities,id'],
            'individual_address.*.permanent_province_id' => ['required', 'exists:provinces,id'],
            'individual_address.*.permanent_region_id' => ['required', 'exists:regions,id'],
            'individual_address.*.permanent_zip_code' => ['required', 'digits:4'],
            'individual_address.*.id' => [
                'nullable',
                'int',
                Rule::exists('individual_addresses', 'id')->where(function ($query) use ($individualId) {
                    if ($individualId) {
                        $query->where('individual_basic_detail_id', $individualId);
                    }
                }),
            ],
            'individual_address.*.residential_house_block_lot_no' => ['nullable', 'string', new DbTextMaxLength()],
            'individual_address.*.residential_street' => ['nullable', 'string', new DbTextMaxLength()],
            'individual_address.*.residential_subdivision_village' => ['nullable', 'string', new DbTextMaxLength()],
            'individual_address.*.permanent_house_block_lot_no' => ['nullable', 'string', new DbTextMaxLength()],
            'individual_address.*.permanent_street' => ['nullable', 'string', new DbTextMaxLength()],
            'individual_address.*.permanent_subdivision_village' => ['nullable', 'string', new DbTextMaxLength()],

            // IndividualContactInfo
            'individual_contact_info' => ['array'],
            'individual_contact_info.*.mobile_no' => [
                'required',
                Rule::unique('user_profiles', 'mobile_number')->ignore(auth()->id(), 'user_id'),
                new InternationalPhoneNumberFormat(),
                (new PhoneRule())->country('PH')->mobile(),
            ],
            'individual_contact_info.*.email_address' => ['nullable', 'email', 'unique:individual_contact_infos,email_address,'.request('individual_contact_info.0.id')], // Get the first record on the array since this is a has one relationship anyway. Ignore uniqueness when id is given.
            'individual_contact_info.*.id' => [
                'nullable',
                'int',
                Rule::exists('individual_contact_infos', 'id')->where(function ($query) use ($individualId) {
                    if ($individualId) {
                        $query->where('individual_basic_detail_id', $individualId);
                    }
                }),
            ],
            'individual_contact_info.*.tel_no' => [
                'nullable',
                new InternationalPhoneNumberFormat(),
                (new PhoneRule())->country('PH')->fixedLine(),
            ],

            // IndividualFamily
            'individual_family' => ['array'],
            'individual_family.*.id' => [
                'nullable',
                'int',
                Rule::exists('individual_families', 'id')->where(function ($query) use ($individualId) {
                    if ($individualId) {
                        $query->where('individual_basic_detail_id', $individualId);
                    }
                }),
            ],
            'individual_family.*.middle_name' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_family.*.ext_name' => ['nullable', new Enum(ExtensionNameCategory::class)],
            'individual_family.*.occupation' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_family.*.employers_business_name' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_family.*.business_address' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_family.*.telephone_no' => [
                'nullable',
                new InternationalPhoneNumberFormat(),
                (new PhoneRule())->country('PH')->mobile(),
            ],
            'individual_family.*._delete' => ['nullable', 'boolean'], // Can delete family members.
            'individual_family.*.first_name' => ['string', 'required', new DbVarcharMaxLength()],
            'individual_family.*.last_name' => ['string', 'required', new DbVarcharMaxLength()],
            'individual_family.*.class' => ['required', new Enum(FamilyMemberCategory::class)],
            'individual_family.*.date_of_birth' => ['required_if:individual_family.*.class,Children', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday],

            // IndividualEducationalBackground
            'individual_educational_background' => ['array'],
            'individual_educational_background.*.level' => ['required', new Enum(AcademicLevel::class)],
            'individual_educational_background.*.id' => [
                'nullable',
                'int',
                Rule::exists('individual_educational_backgrounds', 'id')->where(function ($query) use ($individualId) {
                    if ($individualId) {
                        $query->where('individual_basic_detail_id', $individualId);
                    }
                }),
            ],
            'individual_educational_background.*.schools_name' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_educational_background.*.education_description' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_educational_background.*.period_of_attendance_from' => ['date_format:Y', 'before_or_equal:'.$this->dateToday, 'nullable', new DbVarcharMaxLength()],
            'individual_educational_background.*.period_of_attendance_to' => ['date_format:Y', 'before_or_equal:'.$this->dateToday, 'nullable', new DbVarcharMaxLength()],
            'individual_educational_background.*.highest_level_units_earned' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_educational_background.*.year_graduated' => ['date_format:Y', 'before_or_equal:'.$this->dateToday, 'nullable', new DbVarcharMaxLength()],
            'individual_educational_background.*.scholarship_academic_honors_received' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_educational_background.*._delete' => ['nullable', 'boolean'], // Can delete educational backgrounds.
        ];
    }

    public function getC2Rules(): array
    {
        $individualBasicDetail = $this->route('individualBasicDetail');
        $individualId = $individualBasicDetail ? $individualBasicDetail->id : null;

        return [
            // -----> C2 starts here <-----
            // IndividualEligibilities
            'individual_eligibility' => ['array'],
            'individual_eligibility.*.id' => [
                'nullable',
                'int',
                Rule::exists('individual_eligibilities', 'id')->where(function ($query) use ($individualId) {
                    if ($individualId) {
                        $query->where('individual_basic_detail_id', $individualId);
                    }
                }),
            ],
            'individual_eligibility.*.eligibility' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_eligibility.*.rating' => ['numeric', 'nullable', new DbVarcharMaxLength()],
            'individual_eligibility.*.date_of_examination_conferment' => ['date_format:Y-m-d', 'before_or_equal:'.$this->dateToday, 'nullable', new DbVarcharMaxLength()],
            'individual_eligibility.*.place_of_examination' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_eligibility.*.license_number' => ['string', 'nullable', new DbVarcharMaxLength()],
            'individual_eligibility.*.license_date_of_validity' => ['date_format:Y-m-d', 'nullable', new DbVarcharMaxLength()],

        ];
    }

    public function requiredIfUserIsPPMSAdmin()
    {
        $user = User::find(auth()->user()->id);
        $isPPMSAdmin = $user->hasRole([Role::HR_PPMS_ADMIN->value, Role::ADMIN->value, Role::SUPER_USER->value]);

        return Rule::requiredIf($isPPMSAdmin);
    }

    public function getSearchIndividualRules(): array
    {
        return [
            'query' => ['required', 'string'],
            'limit' => ['nullable', 'int'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.*.id.exists' => 'The :attribute does not belong to the individual you are trying to update.',
        ];
    }
}
