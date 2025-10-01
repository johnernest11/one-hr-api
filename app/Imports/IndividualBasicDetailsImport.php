<?php

namespace App\Imports;

use App\Enums\AcademicLevel;
use App\Enums\BloodType;
use App\Enums\Citizenship;
use App\Enums\CitizenshipAcquisition;
use App\Enums\CivilStatus;
use App\Enums\ExtensionNameCategory;
use App\Enums\FamilyMemberCategory;
use App\Enums\SexualCategory;
use App\Http\Requests\ComprehensiveRecords\IndividualBasicDetailRequest;
use App\Models\Address\Barangay;
use App\Models\Address\City;
use App\Models\Address\Province;
use App\Models\ComprehensiveRecords\IndividualAddress;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\ComprehensiveRecords\IndividualContactInfo;
use Arr;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithMappedCells;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Str;
use TheIconic\NameParser\Parser;

class IndividualBasicDetailsImport implements ToArray, WithMappedCells
{
    private $request;

    private $employeeData;

    private array $individualBasicDetailKeys;

    private array $individualAddressKeys;

    private array $individualContactInfoKeys;

    public $importedRecords;

    protected Parser $nameParser;

    public function __construct(
        array $request,
        Parser $nameParser
    ) {
        $this->request = $request;
        $this->employeeData = $this->processEmployeeData($request);
        $this->importedRecords = new Collection();

        // Initialize Individual Models
        $individualBD = new IndividualBasicDetail();
        $individualAddress = new IndividualAddress();
        $individualContactInfo = new IndividualContactInfo();

        // Get fillable fields for the models.
        $ibdFillable = $individualBD->getFillable();
        $iaFillable = $individualAddress->getFillable();
        $iciFillable = $individualContactInfo->getFillable();

        // Finalize list of fields for import use.
        $this->individualBasicDetailKeys = array_diff($ibdFillable, ['mobile_number', 'telephone_number', 'email']);
        $this->individualAddressKeys = array_diff($iaFillable, ['individual_basic_detail_id']);
        $this->individualContactInfoKeys = array_diff($iciFillable, ['individual_basic_detail_id']);

        $this->nameParser = $nameParser;
    }

    /**
     * Process employee data for validation.
     */
    public function processEmployeeData(array $data): array
    {
        $employee = [
            ...$data,
            'id' => $data['employee_id'],
        ];

        return $employee;
    }

    public function mapping(): array
    {
        return [
            /* -------------------------------------------------------------------------- */
            /*                          Individual Basic Details                          */
            /* -------------------------------------------------------------------------- */
            'first_name' => 'D11',
            'last_name' => 'D10',
            'middle_name' => 'D12',
            'ext_name' => 'L12',
            'birthday' => 'D13',
            'sex' => 'D16',
            'place_of_birth' => 'D15',
            'civil_status' => 'D17',
            'height' => 'D22',
            'weight' => 'D24',
            'blood_type' => 'D25',
            'gsis_no' => 'D27',
            'pag_ibig_no' => 'D29',
            'philhealth_no' => 'D31',
            'sss_no' => 'D32',
            'tin' => 'D33',
            'citizenship_filipino' => 'O13',
            'dual_citizenship' => 'R13',
            'citizenship_by_birth' => 'O15',
            'citizenship_by_naturalization' => 'R15',
            // @todo map country here once it is added

            /* -------------------------------------------------------------------------- */
            /*                             Individual Address                             */
            /* -------------------------------------------------------------------------- */
            'residential_house_block_lot_no' => 'I17',
            'residential_street' => 'L17',
            'residential_subdivision_village' => 'I19',
            'residential_brgy' => 'L19',
            'residential_citymun' => 'I22',
            'residential_province' => 'L22',
            'residential_zip_code' => 'I24',
            'permanent_house_block_lot_no' => 'I25',
            'permanent_street' => 'L25',
            'permanent_subdivision_village' => 'I27',
            'permanent_brgy' => 'L27',
            'permanent_citymun' => 'I29',
            'permanent_province' => 'L29',
            'permanent_zip_code' => 'I31',

            /* -------------------------------------------------------------------------- */
            /*                           Individual Contact Info                          */
            /* -------------------------------------------------------------------------- */
            'tel_no' => 'I32',
            'mobile_no' => 'I33',
            'email_address' => 'I34',

            /* -------------------------------------------------------------------------- */
            /*                              Individual Family                             */
            /* -------------------------------------------------------------------------- */
            'spouse_last_name' => 'D36',
            'spouse_first_name' => 'D37',
            'spouse_middle_name' => 'D38',
            'spouse_ext_name' => 'G38',
            'spouse_occupation' => 'D39',
            'spouse_employers_business_name' => 'D40',
            'spouse_business_address' => 'D41',
            'spouse_telephone_no' => 'D42',

            'father_last_name' => 'D43',
            'father_first_name' => 'D44',
            'father_middle_name' => 'D45',
            'father_ext_name' => 'G45',

            'mother_last_name' => 'D47',
            'mother_first_name' => 'D48',
            'mother_middle_name' => 'D49',

            'children_name_1' => 'I37',
            'children_date_of_birth_1' => 'M37',

            'children_name_2' => 'I38',
            'children_date_of_birth_2' => 'M38',

            'children_name_3' => 'I39',
            'children_date_of_birth_3' => 'M39',

            'children_name_4' => 'I40',
            'children_date_of_birth_4' => 'M40',

            'children_name_5' => 'I41',
            'children_date_of_birth_5' => 'M41',

            'children_name_6' => 'I42',
            'children_date_of_birth_6' => 'M42',

            'children_name_7' => 'I43',
            'children_date_of_birth_7' => 'M43',

            'children_name_8' => 'I44',
            'children_date_of_birth_8' => 'M44',

            'children_name_9' => 'I45',
            'children_date_of_birth_9' => 'M45',

            'children_name_10' => 'I46',
            'children_date_of_birth_10' => 'M46',

            'children_name_11' => 'I47',
            'children_date_of_birth_11' => 'M47',

            'children_name_12' => 'I48',
            'children_date_of_birth_12' => 'M48',

            /* -------------------------------------------------------------------------- */
            /*                      Individual Educational Background                     */
            /* -------------------------------------------------------------------------- */
            'elem_level' => 'B54',
            'elem_schools_name' => 'D54',
            'elem_education_description' => 'G54',
            'elem_period_of_attendance_from' => 'J54',
            'elem_period_of_attendance_to' => 'K54',
            'elem_highest_level_units_earned' => 'L54',
            'elem_year_graduated' => 'M54',
            'elem_scholarship_academic_honors_received' => 'N54',

            'sec_level' => 'B55',
            'sec_schools_name' => 'D55',
            'sec_education_description' => 'G55',
            'sec_period_of_attendance_from' => 'J55',
            'sec_period_of_attendance_to' => 'K55',
            'sec_highest_level_units_earned' => 'L55',
            'sec_year_graduated' => 'M55',
            'sec_scholarship_academic_honors_received' => 'N55',

            'voc_level' => 'B56',
            'voc_schools_name' => 'D56',
            'voc_education_description' => 'G56',
            'voc_period_of_attendance_from' => 'J56',
            'voc_period_of_attendance_to' => 'K56',
            'voc_highest_level_units_earned' => 'L56',
            'voc_year_graduated' => 'M56',
            'voc_scholarship_academic_honors_received' => 'N56',

            'col_level' => 'B57',
            'col_schools_name' => 'D57',
            'col_education_description' => 'G57',
            'col_period_of_attendance_from' => 'J57',
            'col_period_of_attendance_to' => 'K57',
            'col_highest_level_units_earned' => 'L57',
            'col_year_graduated' => 'M57',
            'col_scholarship_academic_honors_received' => 'N57',

            'grad_level' => 'B58',
            'grad_schools_name' => 'D58',
            'grad_education_description' => 'G58',
            'grad_period_of_attendance_from' => 'J58',
            'grad_period_of_attendance_to' => 'K58',
            'grad_highest_level_units_earned' => 'L58',
            'grad_year_graduated' => 'M58',
            'grad_scholarship_academic_honors_received' => 'N58',

            // @todo continue and map the additional sheets
        ];
    }

    public function fuzzyMatch(Builder $query, string $field, ?string $inputValue, int $threshold = 80)
    {
        if ($inputValue) {
            $inputNormalized = Str::lower(trim($inputValue));
            $records = $query->get(); // Get the records before fuzzy matching

            foreach ($records as $r) {
                $value = Str::lower(trim($r->$field));
                similar_text($value, $inputNormalized, $percent);

                if ($percent >= $threshold) {
                    return $r;
                }
            }
        }

        return null;
    }

    public function array(array $row): array
    {
        /* -------------------------------------------------------------------------- */
        /*                                Process Data                                */
        /* -------------------------------------------------------------------------- */
        $row['citizenship'] = $row['dual_citizenship'] ? Citizenship::DUAL_CITIZENSHIP->value : Citizenship::FILIPINO->value;
        $row['citizenship_acquisition'] = $row['citizenship_by_naturalization'] ? CitizenshipAcquisition::NATURALIZATION->value : CitizenshipAcquisition::BIRTH->value;
        $row['birthday'] = $this->safeExcelDateParser($row['birthday']);
        $row['sex'] = $row['sex'] ? $this->matchToEnums(SexualCategory::class, $row['sex'])->value : null;
        $row['civil_status'] = $row['civil_status'] ? $this->matchToEnums(CivilStatus::class, $row['civil_status'])->value : null;
        $row['blood_type'] = $row['blood_type'] ? $this->matchToEnums(BloodType::class, $row['blood_type'])->value : null;
        $row['ext_name'] = $row['ext_name'] ? $this->matchToEnums(ExtensionNameCategory::class, $row['ext_name'])->value : null;

        /* --------------------------------- Address -------------------------------- */
        $row = $this->handleAddressData($row);

        /* --------------------------------- Family --------------------------------- */
        $familyData = $this->handleFamilyData($row);
        $childrenData = $this->handleChildrenData($row);

        /* ------------------------- Educational Background ------------------------- */
        $educationalData = $this->handleEducationData($row);

        /* -------------------------------------------------------------------------- */
        /*                              Restructure Data                              */
        /* -------------------------------------------------------------------------- */
        $restructuredData = [
            'individual' => Arr::only($row, $this->individualBasicDetailKeys),
            'employee' => $this->employeeData,
            'individual_contact_info' => [Arr::only($row, $this->individualContactInfoKeys)],
            'individual_family' => array_merge($familyData, $childrenData),
        ];

        $createRules = new IndividualBasicDetailRequest();

        /* -------------------------- Validate Address Data ------------------------- */
        $addressRequiredFields = collect($createRules->getStoreIndividualRules())
            ->filter(function ($rules, $key) {
                return str_contains($key, 'individual_address')
                    && collect($rules)->contains('required');
            })
            ->keys()
            ->map(fn ($key) => Str::replaceFirst('individual_address.*.', '', $key))
            ->toArray();

        $addressHasNull = $this->checkNullFields($addressRequiredFields, $row);

        // Do not include individual_address to the data if one of the required fields are null.
        if (! $addressHasNull) {
            $restructuredData['individual_address'] = [Arr::only($row, $this->individualAddressKeys)];
        }

        // Add individual_educational_background if it has value
        if ($educationalData) {
            $restructuredData['educations'] = $educationalData;
        }

        /* -------------------------------------------------------------------------- */
        /*                              Return Collection                             */
        /* -------------------------------------------------------------------------- */
        // Push the mapped data into the collection so that it persists outside of this importer.
        // This allows it to be accessed outside of this importer.
        $this->importedRecords = $this->importedRecords->merge($restructuredData);

        return $restructuredData;
    }

    /**
     * Handles family related data except children. Restructures the mapped values into useful data for preview integration.
     */
    public function handleFamilyData(array $row): array
    {
        $prefixes = ['spouse_', 'father_', 'mother_'];
        $groupedData = [];

        // Check civil status and remove 'spouse_' prefix if it's 'Single'
        if (isset($row['civil_status']) && $row['civil_status'] === 'Single') {
            $prefixes = array_filter($prefixes, fn ($prefix) => $prefix !== 'spouse_');
        }

        foreach ($row as $key => $value) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    $newKey = str_replace($prefix, '', $key);
                    $groupName = str_replace('_', '', $prefix);
                    $groupedData[$groupName][$newKey] = $value;

                    break;
                }
            }
        }

        // Add class here
        foreach ($groupedData as $group => $value) {
            $category = Str::studly($group);
            $groupedData[$group]['class'] = FamilyMemberCategory::from($category)->value;
        }

        $finalArray = array_values($groupedData);

        return $finalArray;
    }

    /**
     * Handles children related data. Restructures the mapped values into useful data for preview integration.
     */
    public function handleChildrenData(array $row): array
    {
        $prefix = ['children_' => 'children'];
        $filteredRows = $this->filterPrefixes($prefix, $row);

        $output = [];
        $children = collect($filteredRows)->chunk(2); // Chunk by 2 to group name and birthdate

        foreach ($children as $pair) {
            $nameKey = $pair->keys()->first();
            $name = $pair[$nameKey];
            $bdKey = $pair->keys()->get(1);
            $bd = $pair[$bdKey];

            if (is_null($name) || is_null($bd)) {
                continue;
            }

            // Parse the name using the custom parser
            $parsedName = $this->nameParser->parse($name);

            // Convert the Excel date to Y-m-d format
            $dateOfBirth = $this->safeExcelDateParser($bd);

            // Add the processed data to the output array
            $output[] = [
                'first_name' => $parsedName->getFirstname(),
                'middle_name' => trim($parsedName->getInitials() === '') ? $parsedName->getMiddlename() : $parsedName->getInitials(),
                'last_name' => $parsedName->getLastname(),
                'date_of_birth' => $dateOfBirth,
                'class' => FamilyMemberCategory::CHILDREN->value,
            ];
        }

        return $output;
    }

    /**
     * Handles address related data. Restructures the mapped values into useful data for preview integration.
     */
    public function handleAddressData(array $row): array
    {
        // Start from Province -> get related city -> get related barangay
        $res_province = $this->fuzzyMatch(Province::query(), 'name', $row['residential_province']);
        $res_city = $res_province ? $this->fuzzyMatch(City::query()->where('province_id', $res_province->id), 'name', $row['residential_citymun']) : null;
        $res_brgy = $res_city ? $this->fuzzyMatch(Barangay::query()->where('city_id', $res_city->id), 'name', $row['residential_brgy']) : null;

        $perm_province = $this->fuzzyMatch(Province::query(), 'name', $row['permanent_province']);
        $perm_city = $perm_province ? $this->fuzzyMatch(City::query()->where('province_id', $perm_province->id), 'name', $row['permanent_citymun']) : null;
        $perm_brgy = $perm_city ? $this->fuzzyMatch(Barangay::query()->where('city_id', $perm_city->id), 'name', $row['permanent_brgy']) : null;

        $row['residential_brgy_id'] = $res_brgy ? $res_brgy->id : null;
        $row['residential_citymun_id'] = $res_city ? $res_city->id : null;
        $row['residential_province_id'] = $res_province ? $res_province->id : null;
        $row['residential_region_id'] = $res_province ? $res_province->region->id : null;
        $row['permanent_brgy_id'] = $perm_brgy ? $perm_brgy->id : null;
        $row['permanent_citymun_id'] = $perm_city ? $perm_city->id : null;
        $row['permanent_province_id'] = $perm_province ? $perm_province->id : null;
        $row['permanent_region_id'] = $perm_province ? $perm_province->region->id : null;

        return $row;
    }

    /**
     * Handles educational background data. Restructures the mapped values into useful data for preview integration.
     */
    public function handleEducationData(array $row): array
    {
        $prefixGroups = [
            'elem_' => 'elementary',
            'sec_' => 'high_school',
            'voc_' => 'vocational',
            'col_' => 'college',
            'grad_' => 'graduate',
        ];

        $filteredRows = $this->filterPrefixes($prefixGroups, $row);

        // Ensure that the education levels are in the enums.
        $filteredRows['elem_level'] = AcademicLevel::ELEMENTARY->value;
        $filteredRows['sec_level'] = AcademicLevel::SECONDARY->value;
        $filteredRows['voc_level'] = AcademicLevel::VOCATIONAL->value;
        $filteredRows['col_level'] = AcademicLevel::COLLEGE->value;
        $filteredRows['grad_level'] = AcademicLevel::GRADUATE->value;

        // Transform all into strings for preview integration
        $filteredRows = collect($filteredRows)->map(fn ($value) => (string) $value)->all();

        // If Present is written in Period of Attendance (To),
        // Set is_current_enrolled to True. Else, False.
        $filteredRows['elem_is_current_enrolled'] = strtolower($filteredRows['elem_period_of_attendance_to']) == 'present' ? true : false;
        $filteredRows['sec_is_current_enrolled'] = strtolower($filteredRows['sec_period_of_attendance_to']) == 'present' ? true : false;
        $filteredRows['voc_is_current_enrolled'] = strtolower($filteredRows['voc_period_of_attendance_to']) == 'present' ? true : false;
        $filteredRows['col_is_current_enrolled'] = strtolower($filteredRows['col_period_of_attendance_to']) == 'present' ? true : false;
        $filteredRows['grad_is_current_enrolled'] = strtolower($filteredRows['grad_period_of_attendance_to']) == 'present' ? true : false;

        // Based on the collection of prefixes, group the filtered rows into their own arrays.
        // Remove the prefix on the keys so that it is ready for validation.
        $restructuredData = collect($prefixGroups)->mapWithKeys(function ($groupName, $prefix) use ($filteredRows) {
            $grouped = collect($filteredRows)
                ->filter(fn ($value, $key) => Str::startsWith($key, $prefix))
                ->mapWithKeys(fn ($value, $key) => [Str::after($key, $prefix) => $value])
                ->all();

            return [$groupName => $grouped];
        })->all();

        return $restructuredData;
    }

    /**
     * Fetch key and value pair in an array wherein the key starts with a string that is in the prefixes given.
     */
    public function filterPrefixes(array $prefixes, array $row): array
    {
        $filteredRows = collect($row)->filter(function ($value, $key) use ($prefixes) {
            foreach ($prefixes as $prefix => $value) {
                if (str_starts_with($key, $prefix)) {
                    return true;
                }
            }

            return false;
        }
        )->all();

        return $filteredRows;
    }

    /**
     * Check if there is a null value on an array.
     *
     * @param  array  $rules
     */
    public function checkNullFields(array $inputArray, array $haystack): bool
    {
        return collect($inputArray)
            ->contains(fn ($key) => is_null($haystack[$key] ?? null))
            ? true : false;
    }

    /**
     * Based on $strToMatch, match the string case to compare and find if it is in the passed enum.
     */
    public function matchToEnums(string $enumClass, string $strToMatch)
    {
        // Remove non-alphanumeric characters
        $cleanString = fn (string $str): string => strtolower(
            preg_replace('/[^a-z0-9\s]/i', '', $str)
        );

        $cleanedStrToMatch = $cleanString($strToMatch);

        return collect($enumClass::cases())
            ->first(function ($case) use ($cleanedStrToMatch, $cleanString) {
                return $cleanString($case->value) === $cleanedStrToMatch;
            });
    }

    /**
     * Safely converts a cell value from an Excel import into a 'Y-m-d' date string.
     * Handles numeric Excel dates and attempts to parse common string date formats.
     */
    public function safeExcelDateParser($value): ?string
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        // Check if it's a numeric Excel date (int or float)
        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (Exception $e) {
                return null;
            }
        }

        // Treat as a string (Handles '11/22/1985', 'Nov 22, 1985', etc.)
        if (is_string($value)) {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (Exception $e) {
                return null;
            }
        }

        // Fallback for any other unexpected type
        return null;
    }
}
