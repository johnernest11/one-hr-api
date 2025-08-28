<?php

namespace App\Imports;

use App\Enums\EmploymentStatus;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class C2Import implements ToCollection
{
    public $importedRecords;

    public function __construct()
    {
        $this->importedRecords = new Collection();
    }

    public function collection(Collection $rows)
    {

        /* -------------------------------------------------------------------------- */
        /*                            IndividualEligibility                           */
        /* -------------------------------------------------------------------------- */

        $civilServiceData = $rows->slice(4, 7); // Rows 5-11 (index 4 to 10, with 7 rows)

        $civilServiceColumnsMapping = [ // Map key name and index
            'eligibility' => 0,
            'rating' => 5,
            'date_of_examination_conferment' => 6,
            'place_of_examination' => 8,
            'license_number' => 11,
            'license_date_of_validity' => 12,
        ];

        $extractedCivilServiceData = [];

        foreach ($civilServiceData as $data) {
            $extractedRow = [];

            foreach ($civilServiceColumnsMapping as $keyName => $index) {
                $value = $data->get($index);

                if (isset($value)) {
                    if ($keyName == 'date_of_examination_conferment' || $keyName == 'license_date_of_validity') {
                        $extractedRow[$keyName] = Date::excelToDateTimeObject($value)->format('Y-m-d');

                        continue;
                    }

                    $extractedRow[$keyName] = $value;
                }
            }

            $extractedCivilServiceData[] = $extractedRow;
        }

        /* -------------------------------------------------------------------------- */
        /*                          IndividualWorkExperience                          */
        /* -------------------------------------------------------------------------- */

        $workExpData = $rows->slice(17, 28); // Rows 18-45 (index 17 to 44, with 28 rows)

        $workExpColumnsMapping = [ // Map key name and index
            'inclusive_date_from' => 0,
            'inclusive_date_to' => 2,
            'position_title' => 3,
            'department_agency_office_company' => 6,
            'monthly_salary' => 9,
            'custom_salary_grade' => 10,
            'status_of_appointment' => 11,
            'is_gov_service' => 12,
        ];

        $extractedWorkExpData = [];

        foreach ($workExpData as $data) {
            $extractedRow = [];

            foreach ($workExpColumnsMapping as $keyName => $index) {
                $value = $data->get($index);

                if (isset($value)) {
                    switch ($keyName) {
                        case 'inclusive_date_from':
                        case 'inclusive_date_to':
                            $extractedRow[$keyName] = Date::excelToDateTimeObject($value)->format('Y-m-d');
                            break;

                        case 'status_of_appointment':
                            $extractedRow[$keyName] = $this->matchToEnums(EmploymentStatus::class, $value)->value ?? null;
                            break;

                        case 'is_gov_service':
                            $extractedRow[$keyName] = $this->normalizeYesNo($value);
                            break;

                        default:
                            $extractedRow[$keyName] = $value;
                    }
                }
            }

            $extractedWorkExpData[] = $extractedRow;
        }

        $restructuredData = [
            'individual_eligibility' => $extractedCivilServiceData,
            'individual_work_experience' => $extractedWorkExpData,
        ];

        /* -------------------------------------------------------------------------- */
        /*                              Return Collection                             */
        /* -------------------------------------------------------------------------- */
        // Push the mapped data into the collection so that it persists outside of this importer.
        // This allows it to be accessed outside of this importer.
        $this->importedRecords = $this->importedRecords->merge($restructuredData);

    }

    /**
     * Based on $strToMatch, match the string case to compare and find if it is in the passed enum.
     */
    public function matchToEnums(string $enumClass, string $strToMatch)
    {
        return collect($enumClass::cases())
            ->first(fn ($case) => strtolower($case->value) === strtolower($strToMatch));
    }

    protected function normalizeYesNo($value): ?bool
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        $yesValues = ['yes', 'y', '1', 'true'];
        $noValues = ['no', 'n', '0', 'false'];

        if (in_array($normalized, $yesValues, true)) {
            return true;
        }

        if (in_array($normalized, $noValues, true)) {
            return false;
        }

        return null;
    }
}
