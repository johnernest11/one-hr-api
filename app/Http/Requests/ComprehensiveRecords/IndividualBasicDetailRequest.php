<?php

namespace App\Http\Requests\ComprehensiveRecords;

use App\Enums\AcademicLevel;
use App\Enums\BloodType;
use App\Enums\Citizenship;
use App\Enums\CitizenshipAcquisition;
use App\Enums\CivilStatus;
use App\Enums\EmploymentStatus;
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
            /* -------------------------------------------------------------------------- */
            /*                               C1 starts here                               */
            /* -------------------------------------------------------------------------- */
            /* -------------------------- IndividualBasicDetail ------------------------- */
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

            /* -------------------------------- Employee -------------------------------- */
            'employee' => ['array', $this->requiredIfUserIsPPMSAdmin()],
            'employee.item_id' => ['nullable', 'int', $this->requiredIfUserIsPPMSAdmin()],
            'employee.salary_grade_id' => ['nullable', 'int', $this->requiredIfUserIsPPMSAdmin()],
            'employee.program_id' => ['nullable', 'int'],
            'employee.office_id' => ['nullable', 'int', $this->requiredIfUserIsPPMSAdmin()],
            'employee.division_id' => ['nullable', 'int', $this->requiredIfUserIsPPMSAdmin()],
            'employee.section_or_unit_id' => ['nullable', 'int', $this->requiredIfUserIsPPMSAdmin()],
            'employee.id_number' => ['nullable', 'string', new DbVarcharMaxLength()],
            'employee.agency_employee_no' => ['nullable', 'string', new DbTextMaxLength()],

            /* ---------------------------- IndividualAddress --------------------------- */
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

            /* -------------------------- IndividualContactInfo ------------------------- */
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
                (new PhoneRule())->country('PH')->fixedLine(),
            ],

            /* ---------------------------- IndividualFamily ---------------------------- */
            'individual_family' => ['array'],
            'individual_family.*.middle_name' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_family.*.ext_name' => ['nullable', new Enum(ExtensionNameCategory::class)],
            'individual_family.*.occupation' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_family.*.employers_business_name' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_family.*.business_address' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_family.*.telephone_no' => [
                'nullable',
                new InternationalPhoneNumberFormat(),
                (new PhoneRule())->country('PH')->mobile(),
            ],
            'individual_family.*._delete' => ['nullable', 'boolean'], // Can delete family members.
            'individual_family.*.first_name' => ['required', 'string', new DbVarcharMaxLength()],
            'individual_family.*.last_name' => ['required', 'string', new DbVarcharMaxLength()],
            'individual_family.*.class' => ['required', new Enum(FamilyMemberCategory::class)],
            'individual_family.*.date_of_birth' => ['nullable', 'required_if:individual_family.*.class,Children', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday],

            /* --------------------- IndividualEducationalBackground -------------------- */
            'individual_educational_background' => ['array'],
            'individual_educational_background.*.level' => ['required', new Enum(AcademicLevel::class)],
            'individual_educational_background.*.schools_name' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_educational_background.*.education_description' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_educational_background.*.period_of_attendance_from' => ['nullable', 'date_format:Y', 'before_or_equal:'.$this->dateToday, new DbVarcharMaxLength()],
            'individual_educational_background.*.period_of_attendance_to' => ['nullable', 'date_format:Y', 'before_or_equal:'.$this->dateToday, new DbVarcharMaxLength()],
            'individual_educational_background.*.highest_level_units_earned' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_educational_background.*.year_graduated' => ['nullable', 'date_format:Y', 'before_or_equal:'.$this->dateToday, new DbVarcharMaxLength()],
            'individual_educational_background.*.scholarship_academic_honors_received' => ['nullable', 'string', new DbVarcharMaxLength()],

            /* -------------------------------------------------------------------------- */
            /*                               C2 starts here                               */
            /* -------------------------------------------------------------------------- */
            /* ------------------------- IndividualEligibilities ------------------------ */
            'individual_eligibility' => ['array'],
            'individual_eligibility.*.eligibility' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_eligibility.*.rating' => ['nullable', 'numeric', new DbVarcharMaxLength()],
            'individual_eligibility.*.date_of_examination_conferment' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday, new DbVarcharMaxLength()],
            'individual_eligibility.*.place_of_examination' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_eligibility.*.license_number' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_eligibility.*.license_date_of_validity' => ['nullable', 'date_format:Y-m-d', new DbVarcharMaxLength()],

            /* ------------------------ IndividualWorkExperience ------------------------ */
            'individual_work_experience' => ['array'],
            'individual_work_experience.*.is_current_work' => ['nullable', 'boolean'],
            'individual_work_experience.*.inclusive_date_from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday],
            'individual_work_experience.*.inclusive_date_to' => ['nullable', 'date_format:Y-m-d'],
            'individual_work_experience.*.position_title' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_work_experience.*.department_agency_office_company' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_work_experience.*.monthly_salary' => ['nullable', 'numeric'],
            'individual_work_experience.*.salary_grade_id' => ['nullable', 'int'],
            'individual_work_experience.*.custom_salary_grade' => ['nullable', 'string', 'regex:/^\d{2}-\d{1}$/'], // For when it does not exist in the salary grade library
            'individual_work_experience.*.status_of_appointment' => ['nullable', new Enum(EmploymentStatus::class)],
            'individual_work_experience.*.is_gov_service' => ['nullable', 'boolean'],

            /* -------------------------------------------------------------------------- */
            /*                               C3 starts here                               */
            /* -------------------------------------------------------------------------- */
            /* ------------------------- IndividualVoluntaryWork ------------------------ */
            'individual_voluntary_work' => ['array'],
            'individual_voluntary_work.*.is_current_org' => ['nullable', 'boolean'],
            'individual_voluntary_work.*.org_name' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_voluntary_work.*.org_address' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_voluntary_work.*.from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday],
            'individual_voluntary_work.*.to' => ['nullable', 'date_format:Y-m-d'],
            'individual_voluntary_work.*.number_of_hours' => ['nullable', 'numeric'],
            'individual_voluntary_work.*.position_nature_of_work' => ['nullable', 'string', new DbVarcharMaxLength()],

            /* ------------------------------ IndividualLnd ----------------------------- */
            'individual_lnd' => ['array'],
            'individual_lnd.*.title' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_lnd.*.from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday],
            'individual_lnd.*.to' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday],
            'individual_lnd.*.number_of_hours' => ['nullable', 'integer'],
            'individual_lnd.*.type' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_lnd.*.conducted_sponsor' => ['nullable', 'string', new DbVarcharMaxLength()],

            /* -------------------------- IndividualSkillsHobby ------------------------- */
            'individual_skills_hobby' => ['array'],
            'individual_skills_hobby.*.skill_hobby' => ['nullable', 'string', new DbVarcharMaxLength()],

            /* -------------------------- IndividualRecognition ------------------------- */
            'individual_recognition' => ['array'],
            'individual_recognition.*.recognition' => ['nullable', 'string', new DbVarcharMaxLength()],

            /* -------------------------- IndividualMembership -------------------------- */
            'individual_membership' => ['array'],
            'individual_membership.*.association_organization' => ['nullable', 'string', new DbVarcharMaxLength()],

            /* -------------------------------------------------------------------------- */
            /*                               C4 starts here                               */
            /* -------------------------------------------------------------------------- */
            /* --------------------------- IndividualQuestion --------------------------- */
            'individual_question' => ['array'],
            /* ------------------------------- Question 34 ------------------------------ */
            'individual_question.*.q34_a' => ['nullable', 'boolean'],
            'individual_question.*.q34_b' => ['nullable', 'boolean'],
            'individual_question.*.q34_details' => ['nullable', 'required_if:individual_question.*.q34_a,true', 'required_if:individual_question.*.q34_b,true', 'string', new DbVarcharMaxLength()],
            /* ------------------------------- Question 35 ------------------------------ */
            'individual_question.*.q35_a' => ['nullable', 'boolean'],
            'individual_question.*.q35_a_details' => ['nullable', 'required_if:individual_question.*.q35_a,true', 'string', new DbVarcharMaxLength()],
            'individual_question.*.q35_b' => ['nullable', 'boolean'],
            'individual_question.*.q35_b_date_filed' => ['nullable', 'required_if:individual_question.*.q35_b,true', 'date_format:Y-m-d'],
            'individual_question.*.q35_b_status' => ['nullable', 'required_if:individual_question.*.q35_b,true', 'string', new DbVarcharMaxLength()],
            /* ------------------------------- Question 36 ------------------------------ */
            'individual_question.*.q36' => ['nullable', 'boolean'],
            'individual_question.*.q36_details' => ['nullable', 'required_if:individual_question.*.q36,true', 'string', new DbVarcharMaxLength()],
            /* ------------------------------- Question 37 ------------------------------ */
            'individual_question.*.q37' => ['nullable', 'boolean'],
            'individual_question.*.q37_details' => ['nullable', 'required_if:individual_question.*.q37,true', 'string', new DbVarcharMaxLength()],
            /* ------------------------------- Question 38 ------------------------------ */
            'individual_question.*.q38_a' => ['nullable', 'boolean'],
            'individual_question.*.q38_a_details' => ['nullable', 'required_if:individual_question.*.q38_a,true', 'string', new DbVarcharMaxLength()],
            'individual_question.*.q38_b' => ['nullable', 'boolean'],
            'individual_question.*.q38_b_details' => ['nullable', 'required_if:individual_question.*.q38_b,true', 'string', new DbVarcharMaxLength()],
            /* ------------------------------- Question 39 ------------------------------ */
            'individual_question.*.q39' => ['nullable', 'boolean'],
            'individual_question.*.country_id' => ['nullable', 'required_if:individual_question.*.q39,true', 'exists:countries,id', 'int'], // for ensuring that the id is a valid country
            /* ------------------------------- Question 40 ------------------------------ */
            'individual_question.*.q40_a_indigenous_group' => ['nullable', 'boolean'],
            'individual_question.*.q40_a_details' => ['nullable', 'required_if:individual_question.*.q40_a_indigenous_group,true', 'string', new DbVarcharMaxLength()],
            'individual_question.*.q40_b_pwd' => ['nullable', 'boolean'],
            'individual_question.*.q40_b_details' => ['nullable', 'required_if:individual_question.*.q40_b_pwd,true', 'string', new DbVarcharMaxLength()],
            'individual_question.*.q40_c_solo_parent' => ['nullable', 'boolean'],
            'individual_question.*.q40_c_details' => ['nullable', 'required_if:individual_question.*.q40_c_solo_parent,true', 'string', new DbVarcharMaxLength()],

            /* --------------------------- IndividualReference -------------------------- */
            'individual_reference' => ['array', 'max:3'], // Maximum of 3 references allowed per individual
            'individual_reference.*.name' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_reference.*.address' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_reference.*.tel_no' => ['nullable',
                (new PhoneRule())->country('PH')], // Can be either mobile or tele
        ];
    }

    public function getFormTypeRules(): array
    {
        return [
            'form_type' => ['required', new Enum(PDSFormType::class)],
        ];
    }

    public function getUpdateIndividualRules(): array
    {
        $form_rules = $this->getFormTypeRules();
        // Get which form will be updated.
        $form_type = $this->input('form_type');

        // Check which form will be updated and return the appropriate rules.
        $rules = match ($form_type) {
            PDSFormType::C1->value => $this->getC1Rules(),
            PDSFormType::C2->value => $this->getC2Rules(),
            PDSFormType::C3->value => $this->getC3Rules(),
            PDSFormType::C4->value => $this->getC4Rules(),
            default => []
        };

        return array_merge($form_rules, $rules);
    }

    public function getC1Rules(): array
    {
        return [
            /* -------------------------------------------------------------------------- */
            /*                               C1 starts here                               */
            /* -------------------------------------------------------------------------- */
            /* -------------------------- IndividualBasicDetail ------------------------- */
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

            /* -------------------------------- Employee -------------------------------- */
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
                $this->validateRecordID('employees'),
            ],
            'employee.id_number' => ['nullable', 'string', new DbVarcharMaxLength()],
            'employee.agency_employee_no' => ['nullable', 'string', new DbTextMaxLength()],

            /* ---------------------------- IndividualAddress --------------------------- */
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
                $this->validateRecordID('individual_addresses'),
            ],
            'individual_address.*.residential_house_block_lot_no' => ['nullable', 'string', new DbTextMaxLength()],
            'individual_address.*.residential_street' => ['nullable', 'string', new DbTextMaxLength()],
            'individual_address.*.residential_subdivision_village' => ['nullable', 'string', new DbTextMaxLength()],
            'individual_address.*.permanent_house_block_lot_no' => ['nullable', 'string', new DbTextMaxLength()],
            'individual_address.*.permanent_street' => ['nullable', 'string', new DbTextMaxLength()],
            'individual_address.*.permanent_subdivision_village' => ['nullable', 'string', new DbTextMaxLength()],

            /* -------------------------- IndividualContactInfo ------------------------- */
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
                $this->validateRecordID('individual_contact_infos'),
            ],
            'individual_contact_info.*.tel_no' => [
                'nullable',
                new InternationalPhoneNumberFormat(),
                (new PhoneRule())->country('PH')->fixedLine(),
            ],

            /* ---------------------------- IndividualFamily ---------------------------- */
            'individual_family' => ['array'],
            'individual_family.*.id' => [
                'nullable',
                'int',
                $this->validateRecordID('individual_families'),
            ],
            'individual_family.*.middle_name' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_family.*.ext_name' => ['nullable', new Enum(ExtensionNameCategory::class)],
            'individual_family.*.occupation' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_family.*.employers_business_name' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_family.*.business_address' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_family.*.telephone_no' => [
                'nullable',
                new InternationalPhoneNumberFormat(),
                (new PhoneRule())->country('PH')->mobile(),
            ],
            'individual_family.*._delete' => ['nullable', 'boolean'], // Can delete family members.
            'individual_family.*.first_name' => ['required', 'string', new DbVarcharMaxLength()],
            'individual_family.*.last_name' => ['required', 'string', new DbVarcharMaxLength()],
            'individual_family.*.class' => ['required', new Enum(FamilyMemberCategory::class)],
            'individual_family.*.date_of_birth' => ['nullable', 'required_if:individual_family.*.class,Children', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday],

            /* --------------------- IndividualEducationalBackground -------------------- */
            'individual_educational_background' => ['array'],
            'individual_educational_background.*.level' => ['required', new Enum(AcademicLevel::class)],
            'individual_educational_background.*.id' => [
                'nullable',
                'int',
                $this->validateRecordID('individual_educational_backgrounds'),
            ],
            'individual_educational_background.*.schools_name' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_educational_background.*.education_description' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_educational_background.*.period_of_attendance_from' => ['nullable', 'date_format:Y', 'before_or_equal:'.$this->dateToday, new DbVarcharMaxLength()],
            'individual_educational_background.*.period_of_attendance_to' => ['nullable', 'date_format:Y', 'before_or_equal:'.$this->dateToday, new DbVarcharMaxLength()],
            'individual_educational_background.*.highest_level_units_earned' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_educational_background.*.year_graduated' => ['nullable', 'date_format:Y', 'before_or_equal:'.$this->dateToday, new DbVarcharMaxLength()],
            'individual_educational_background.*.scholarship_academic_honors_received' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_educational_background.*._delete' => ['nullable', 'boolean'], // Can delete educational backgrounds.
        ];
    }

    public function getC2Rules(): array
    {
        return [
            /* -------------------------------------------------------------------------- */
            /*                               C2 starts here                               */
            /* -------------------------------------------------------------------------- */
            /* ------------------------- IndividualEligibilities ------------------------ */
            'individual_eligibility' => ['array'],
            'individual_eligibility.*.id' => [
                'nullable',
                'int',
                $this->validateRecordID('individual_eligibilities'),
            ],
            'individual_eligibility.*.eligibility' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_eligibility.*.rating' => ['nullable', 'numeric', new DbVarcharMaxLength()],
            'individual_eligibility.*.date_of_examination_conferment' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday, new DbVarcharMaxLength()],
            'individual_eligibility.*.place_of_examination' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_eligibility.*.license_number' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_eligibility.*.license_date_of_validity' => ['nullable', 'date_format:Y-m-d', new DbVarcharMaxLength()],
            'individual_eligibility.*._delete' => ['nullable', 'boolean'], // Can delete eligibilities.

            /* ------------------------ IndividualWorkExperience ------------------------ */
            'individual_work_experience' => ['array'],
            'individual_work_experience.*.id' => [
                'nullable',
                'int',
                $this->validateRecordID('individual_work_experiences'),
            ],
            'individual_work_experience.*.is_current_work' => ['nullable', 'boolean'],
            'individual_work_experience.*.inclusive_date_from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday],
            'individual_work_experience.*.inclusive_date_to' => ['nullable', 'date_format:Y-m-d'],
            'individual_work_experience.*.position_title' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_work_experience.*.department_agency_office_company' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_work_experience.*.monthly_salary' => ['nullable', 'numeric'],
            'individual_work_experience.*.salary_grade_id' => ['nullable', 'int'],
            'individual_work_experience.*.custom_salary_grade' => ['nullable', 'string', 'regex:/^\d{2}-\d{1}$/'], // For when it does not exist in the salary grade library
            'individual_work_experience.*.status_of_appointment' => ['nullable', new Enum(EmploymentStatus::class)],
            'individual_work_experience.*.is_gov_service' => ['nullable', 'boolean'],
            'individual_work_experience.*._delete' => ['nullable', 'boolean'], // Can delete work experience.
        ];
    }

    public function getC3Rules(): array
    {
        return [
            /* -------------------------------------------------------------------------- */
            /*                               C3 starts here                               */
            /* -------------------------------------------------------------------------- */
            /* ------------------------- IndividualVoluntaryWork ------------------------ */
            'individual_voluntary_work' => ['array'],
            'individual_voluntary_work.*.id' => [
                'nullable',
                'int',
                $this->validateRecordID('individual_voluntary_works'),
            ],
            'individual_voluntary_work.*.is_current_org' => ['nullable', 'boolean'],
            'individual_voluntary_work.*.org_name' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_voluntary_work.*.org_address' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_voluntary_work.*.from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday],
            'individual_voluntary_work.*.to' => ['nullable', 'date_format:Y-m-d'],
            'individual_voluntary_work.*.number_of_hours' => ['nullable', 'numeric'],
            'individual_voluntary_work.*.position_nature_of_work' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_voluntary_work.*._delete' => ['nullable', 'boolean'], // Can delete voluntary work.

            /* ------------------------------ IndividualLnd ----------------------------- */
            'individual_lnd' => ['array'],
            'individual_lnd.*.id' => [
                'nullable',
                'int',
                $this->validateRecordID('individual_lnds'),
            ],
            'individual_lnd.*.title' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_lnd.*.from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday],
            'individual_lnd.*.to' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$this->dateToday],
            'individual_lnd.*.number_of_hours' => ['nullable', 'integer'],
            'individual_lnd.*.type' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_lnd.*.conducted_sponsor' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_lnd.*._delete' => ['nullable', 'boolean'], // Can delete lnd

            /* -------------------------- IndividualSkillsHobby ------------------------- */
            'individual_skills_hobby' => ['array'],
            'individual_skills_hobby.*.id' => [
                'nullable',
                'int',
                $this->validateRecordID('individual_skills_hobbies'),
            ],
            'individual_skills_hobby.*.skill_hobby' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_skills_hobby.*._delete' => ['nullable', 'boolean'], // Can delete lnd

            /* -------------------------- IndividualRecognition ------------------------- */
            'individual_recognition' => ['array'],
            'individual_recognition.*.id' => [
                'nullable',
                'int',
                $this->validateRecordID('individual_recognitions'),
            ],
            'individual_recognition.*.recognition' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_recognition.*._delete' => ['nullable', 'boolean'], // Can delete lnd

            /* -------------------------- IndividualMembership -------------------------- */
            'individual_membership' => ['array'],
            'individual_membership.*.id' => [
                'nullable',
                'int',
                $this->validateRecordID('individual_memberships'),
            ],
            'individual_membership.*.association_organization' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_membership.*._delete' => ['nullable', 'boolean'], // Can delete lnd
        ];
    }

    public function getC4Rules(): array
    {
        return [
            /* -------------------------------------------------------------------------- */
            /*                               C4 starts here                               */
            /* -------------------------------------------------------------------------- */
            /* --------------------------- IndividualQuestion --------------------------- */
            'individual_question' => ['array'],
            'individual_question.*.id' => [
                'nullable',
                'int',
                $this->validateRecordID('individual_questions'),
            ],
            /* ------------------------------- Question 34 ------------------------------ */
            'individual_question.*.q34_a' => ['nullable', 'boolean'],
            'individual_question.*.q34_b' => ['nullable', 'boolean'],
            'individual_question.*.q34_details' => ['nullable', 'required_if:individual_question.*.q34_a,true', 'required_if:individual_question.*.q34_b,true', 'string', new DbVarcharMaxLength()],
            /* ------------------------------- Question 35 ------------------------------ */
            'individual_question.*.q35_a' => ['nullable', 'boolean'],
            'individual_question.*.q35_a_details' => ['nullable', 'required_if:individual_question.*.q35_a,true', 'string', new DbVarcharMaxLength()],
            'individual_question.*.q35_b' => ['nullable', 'boolean'],
            'individual_question.*.q35_b_date_filed' => ['nullable', 'required_if:individual_question.*.q35_b,true', 'date_format:Y-m-d'],
            'individual_question.*.q35_b_status' => ['nullable', 'required_if:individual_question.*.q35_b,true', 'string', new DbVarcharMaxLength()],
            /* ------------------------------- Question 36 ------------------------------ */
            'individual_question.*.q36' => ['nullable', 'boolean'],
            'individual_question.*.q36_details' => ['nullable', 'required_if:individual_question.*.q36,true', 'string', new DbVarcharMaxLength()],
            /* ------------------------------- Question 37 ------------------------------ */
            'individual_question.*.q37' => ['nullable', 'boolean'],
            'individual_question.*.q37_details' => ['nullable', 'required_if:individual_question.*.q37,true', 'string', new DbVarcharMaxLength()],
            /* ------------------------------- Question 38 ------------------------------ */
            'individual_question.*.q38_a' => ['nullable', 'boolean'],
            'individual_question.*.q38_a_details' => ['nullable', 'required_if:individual_question.*.q38_a,true', 'string', new DbVarcharMaxLength()],
            'individual_question.*.q38_b' => ['nullable', 'boolean'],
            'individual_question.*.q38_b_details' => ['nullable', 'required_if:individual_question.*.q38_b,true', 'string', new DbVarcharMaxLength()],
            /* ------------------------------- Question 39 ------------------------------ */
            'individual_question.*.q39' => ['nullable', 'boolean'],
            'individual_question.*.country_id' => ['nullable', 'required_if:individual_question.*.q39,true', 'exists:countries,id', 'int'], // for ensuring that the id is a valid country
            /* ------------------------------- Question 40 ------------------------------ */
            'individual_question.*.q40_a_indigenous_group' => ['nullable', 'boolean'],
            'individual_question.*.q40_a_details' => ['nullable', 'required_if:individual_question.*.q40_a_indigenous_group,true', 'string', new DbVarcharMaxLength()],
            'individual_question.*.q40_b_pwd' => ['nullable', 'boolean'],
            'individual_question.*.q40_b_details' => ['nullable', 'required_if:individual_question.*.q40_b_pwd,true', 'string', new DbVarcharMaxLength()],
            'individual_question.*.q40_c_solo_parent' => ['nullable', 'boolean'],
            'individual_question.*.q40_c_details' => ['nullable', 'required_if:individual_question.*.q40_c_solo_parent,true', 'string', new DbVarcharMaxLength()],

            /* --------------------------- IndividualReference -------------------------- */
            'individual_reference' => ['array', 'max:3'], // Maximum of 3 references allowed per individual
            'individual_reference.*.id' => [
                'nullable',
                'int',
                $this->validateRecordID('individual_references'),
            ],
            'individual_reference.*.name' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_reference.*.address' => ['nullable', 'string', new DbVarcharMaxLength()],
            'individual_reference.*.tel_no' => ['nullable',
                new InternationalPhoneNumberFormat(),
                (new PhoneRule())->country('PH')], // Can be either mobile or tele
            'individual_reference.*._delete' => ['nullable', 'boolean'], // Can delete references
        ];
    }

    public function validateRecordID($table_name)
    {
        $individualBasicDetail = $this->route('individualBasicDetail');
        $individualId = $individualBasicDetail ? $individualBasicDetail->id : null;

        return Rule::exists($table_name, 'id')->where(function ($query) use ($individualId) {
            if ($individualId) {
                $query->where('individual_basic_detail_id', $individualId);
            }
        });
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
            'individual_question.*.countries_ids.*.exists' => 'The ID in :attribute does not exist in the countries library.',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // This is to throw error if there are more than one record with is_current_work = true in the request.
            $workExperiences = $this->input('individual_work_experience', []);

            $currentWorkCount = 0;
            foreach ($workExperiences as $experience) {
                if (isset($experience['is_current_work']) && $experience['is_current_work']) {
                    $currentWorkCount++;
                }
            }

            if ($currentWorkCount > 1) {
                $validator->errors()->add(
                    'individual_work_experience.*.is_current_work',
                    'Only one work experience can be set as the current work.'
                );
            }
        });
    }
}
