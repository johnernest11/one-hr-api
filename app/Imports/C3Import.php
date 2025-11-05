<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class C3Import implements ToCollection
{
    public $importedRecords;

    public function __construct()
    {
        $this->importedRecords = new Collection;
    }

    public function collection(Collection $rows)
    {

        /* -------------------------------------------------------------------------- */
        /*                           IndividualVoluntaryWork */
        /* -------------------------------------------------------------------------- */
        $voluntaryWorkData = $rows->slice(5, 7); // Rows 6-12

        $voluntaryWorkColumnsMapping = [ // Map key name and index
            'org_name' => 0,
            'org_address' => 2, // Added a specific cell for this in the template.
            'from' => 4,
            'to' => 5,
            'number_of_hours' => 6,
            'position_nature_of_work' => 7,
        ];

        $extractedVoluntaryWorkData = [];

        foreach ($voluntaryWorkData as $data) {
            $extractedRow = [];

            foreach ($voluntaryWorkColumnsMapping as $keyName => $index) {
                $value = $data->get($index);

                if (isset($value)) {
                    if ($keyName == 'from' || $keyName == 'to') {
                        $extractedRow[$keyName] = Date::excelToDateTimeObject($value)->format('Y-m-d');

                        continue;
                    }

                    $extractedRow[$keyName] = $value;
                }
            }

            $extractedVoluntaryWorkData[] = $extractedRow;
        }

        /* -------------------------------------------------------------------------- */
        /*                                IndividualLnd */
        /* -------------------------------------------------------------------------- */
        $lndData = $rows->slice(17, 21); // Rows 18-38

        $lndColumnsMapping = [ // Map key name and index
            'title' => 0,
            'from' => 4,
            'to' => 5,
            'number_of_hours' => 6,
            'type' => 7,
            'conducted_sponsor' => 8,
        ];

        $extractedLndData = [];

        foreach ($lndData as $data) {
            $extractedRow = [];

            foreach ($lndColumnsMapping as $keyName => $index) {
                $value = $data->get($index);

                if (isset($value)) {
                    if ($keyName == 'from' || $keyName == 'to') {
                        $extractedRow[$keyName] = Date::excelToDateTimeObject($value)->format('Y-m-d');

                        continue;
                    }

                    $extractedRow[$keyName] = $value;
                }
            }

            $extractedLndData[] = $extractedRow;
        }

        /* -------------------------------------------------------------------------- */
        /*    IndividualSkillsHobby, IndividualRecognition, & IndividualMembership */
        /* -------------------------------------------------------------------------- */
        $hobbyRecogMembershipData = $rows->slice(41, 7); // Rows 42-48

        $remainingColumnsMapping = [ // Map key name and index
            'skill_hobby' => 0,
            'recognition' => 2,
            'association_organization' => 8,
        ];

        $extractedHobbyData = [];
        $extractedRecogData = [];
        $extractedMembershipData = [];

        foreach ($hobbyRecogMembershipData as $data) {
            $extractedHobbyRow = [];
            $extractedRecogRow = [];
            $extractedMembershipRow = [];

            foreach ($remainingColumnsMapping as $keyName => $index) {
                $value = $data->get($index);

                if (isset($value)) {
                    switch ($keyName) {
                        case 'skill_hobby':
                            $extractedHobbyRow[$keyName] = $value;
                            break;
                        case 'recognition':
                            $extractedRecogRow[$keyName] = $value;
                            break;
                        case 'association_organization':
                            $extractedMembershipRow[$keyName] = $value;
                            break;
                    }

                }
            }

            $extractedHobbyData[] = $extractedHobbyRow;
            $extractedRecogData[] = $extractedRecogRow;
            $extractedMembershipData[] = $extractedMembershipRow;
        }

        $restructuredData = [
            'individual_voluntary_work' => $extractedVoluntaryWorkData,
            'individual_lnd' => $extractedLndData,
            'individual_skills_hobby' => $extractedHobbyData,
            'individual_recognition' => $extractedRecogData,
            'individual_membership' => $extractedMembershipData,
        ];

        /* -------------------------------------------------------------------------- */
        /*                              Return Collection */
        /* -------------------------------------------------------------------------- */
        // Push the mapped data into the collection so that it persists outside of this importer.
        // This allows it to be accessed outside of this importer.
        $this->importedRecords = $this->importedRecords->merge($restructuredData);

    }
}
