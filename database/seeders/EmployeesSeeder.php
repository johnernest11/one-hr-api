<?php

namespace Database\Seeders;

use App\Models\ComprehensiveRecords\Employee;
use App\Models\ComprehensiveRecords\IndividualAddress;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\ComprehensiveRecords\IndividualContactInfo;
use App\Models\ComprehensiveRecords\IndividualEducationalBackground;
use App\Models\ComprehensiveRecords\IndividualEligibility;
use App\Models\ComprehensiveRecords\IndividualQuestion;
use App\Models\ComprehensiveRecords\IndividualWorkExperience;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeesSeeder extends CiCdCompliantSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key constraints globally for this seeder
        Schema::disableForeignKeyConstraints();

        DB::transaction(function () {
            $now = Carbon::now();

            // Helper lambda to chunk, insert, and debug errors down to the single failing row
            $insertWithDebugging = function (string $tableName, string $modelClass, array $dataList) {
                if (empty($dataList)) {
                    return;
                }

                foreach (array_chunk($dataList, 500) as $chunkIndex => $chunk) {
                    try {
                        $modelClass::insert($chunk);
                    } catch (QueryException $e) {
                        // Isolate the exact failing row inside this chunk
                        foreach ($chunk as $rowIndex => $singleRecord) {
                            try {
                                $modelClass::insert([$singleRecord]);
                            } catch (QueryException $singleException) {
                                $recordNumber = ($chunkIndex * 500) + $rowIndex + 1;

                                dd([
                                    'FAILED TABLE' => $tableName,
                                    'ERROR MESSAGE' => $singleException->getMessage(),
                                    'FAILED RECORD' => "Row #{$recordNumber} out of ".count($dataList),
                                    'BAD RECORD DATA' => $singleRecord,
                                ]);
                            }
                        }
                    }
                }
            };

            // 1. Individual Basic Details
            $basicDetailsData = json_decode(file_get_contents(base_path('database/seeders/dumps/individual_basic_details.json')), true) ?? [];

            $basicDetails = array_map(function ($item) use ($now) {
                foreach ($item as $key => $value) {
                    if (is_array($value)) {
                        $item[$key] = json_encode($value);
                    }
                }

                // Force fallback date for null or empty birthday values
                if (empty($item['birthday']) || $item['birthday'] === 'null') {
                    $item['birthday'] = '1900-01-01';
                }

                $item['created_at'] = $now;
                $item['updated_at'] = $now;

                return $item;
            }, $basicDetailsData);
            $insertWithDebugging('individual_basic_details', IndividualBasicDetail::class, $basicDetails);

            // 2. Employees Duplicate Diagnostic
            $employeesData = json_decode(file_get_contents(base_path('database/seeders/dumps/employees.json')), true) ?? [];

            // // Group records by id_number to track collisions with primary keys
            // $idNumberCounts = [];
            // foreach ($employeesData as $index => $emp) {
            //     $idNum = trim($emp['id_number'] ?? '');
            //     if ($idNum !== '') {
            //         $idNumberCounts[$idNum][] = [
            //             'employee_id'                => $emp['id'] ?? null,
            //             'individual_basic_detail_id' => $emp['individual_basic_detail_id'] ?? null,
            //             'json_index'                 => $index,
            //         ];
            //     }
            // }

            // // Filter to show ONLY duplicate entries
            // $duplicates = array_filter($idNumberCounts, function ($occurrences) {
            //     return count($occurrences) > 1;
            // });

            // // Dump all duplicates with employee_ids and halt execution
            // if (!empty($duplicates)) {
            //     dd([
            //         'TOTAL DUPLICATE KEYS'  => count($duplicates),
            //         'DUPLICATED ID NUMBERS' => $duplicates,
            //     ]);
            // }

            // 2. Employees Processing
            $employees = array_map(function ($item) use ($now) {
                // Force default office_id if null, missing, or 'null'
                if (empty($item['office_id']) || $item['office_id'] === 'null') {
                    $item['office_id'] = 1; // Default fallback foreign key ID
                }

                // Handle missing or null item_id explicitly
                if (! isset($item['item_id']) || is_null($item['item_id']) || $item['item_id'] === 'null' || $item['item_id'] === '') {
                    $item['item_id'] = 1; // Default fallback item ID
                }

                $item['created_at'] = $now;
                $item['updated_at'] = $now;

                return $item;
            }, $employeesData);

            $insertWithDebugging('employees', Employee::class, $employees);

            // 3. Individual Addresses
            $addressesData = json_decode(file_get_contents(base_path('database/seeders/dumps/individual_addresses.json')), true) ?? [];
            $addresses = array_map(function ($item) use ($now) {
                $item['created_at'] = $now;
                $item['updated_at'] = $now;

                return $item;
            }, $addressesData);
            $insertWithDebugging('individual_addresses', IndividualAddress::class, $addresses);

            // 4. Individual Contact Infos
            $contactsData = json_decode(file_get_contents(base_path('database/seeders/dumps/individual_contact_infos.json')), true) ?? [];
            $contacts = array_map(function ($item) use ($now) {
                $item['created_at'] = $now;
                $item['updated_at'] = $now;

                return $item;
            }, $contactsData);
            $insertWithDebugging('individual_contact_infos', IndividualContactInfo::class, $contacts);

            // 5. Individual Educational Backgrounds
            $educationData = json_decode(file_get_contents(base_path('database/seeders/dumps/individual_educational_backgrounds.json')), true) ?? [];
            $education = array_map(function ($item) use ($now) {
                $item['created_at'] = $now;
                $item['updated_at'] = $now;

                return $item;
            }, $educationData);
            $insertWithDebugging('individual_educational_backgrounds', IndividualEducationalBackground::class, $education);

            // 6. Individual Eligibilities
            $eligibilityData = json_decode(file_get_contents(base_path('database/seeders/dumps/individual_eligibilities.json')), true) ?? [];
            $eligibility = array_map(function ($item) use ($now) {
                $item['created_at'] = $now;
                $item['updated_at'] = $now;

                return $item;
            }, $eligibilityData);
            $insertWithDebugging('individual_eligibilities', IndividualEligibility::class, $eligibility);

            // 7. Individual Questions
            $questionsData = json_decode(file_get_contents(base_path('database/seeders/dumps/individual_questions.json')), true) ?? [];
            $questions = array_map(function ($item) use ($now) {
                $item['created_at'] = $now;
                $item['updated_at'] = $now;

                return $item;
            }, $questionsData);
            $insertWithDebugging('individual_questions', IndividualQuestion::class, $questions);

            // 8. Individual Work Experiences
            $workExperiencesData = json_decode(file_get_contents(base_path('database/seeders/dumps/individual_work_experiences.json')), true) ?? [];
            $workExperiences = array_map(function ($item) use ($now) {
                $item['created_at'] = $now;
                $item['updated_at'] = $now;

                return $item;
            }, $workExperiencesData);
            $insertWithDebugging('individual_work_experiences', IndividualWorkExperience::class, $workExperiences);
        });

        // Re-enable foreign key constraints
        Schema::enableForeignKeyConstraints();
    }

    protected function tableName(): string
    {
        return app(IndividualBasicDetail::class)->getTable();
    }

    /** {@inheritDoc} */
    public function shouldRun(): bool
    {
        return $this->tableIsEmpty();
    }
}
