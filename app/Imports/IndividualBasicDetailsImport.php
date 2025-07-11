<?php

namespace App\Imports;

use App\Enums\Citizenship;
use App\Enums\CitizenshipAcquisition;
use App\Http\Requests\ComprehensiveRecords\IndividualBasicDetailRequest;
use App\Services\ComprehensiveRecords\IndividualBasicDetailManager;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithMappedCells;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Validator;

class IndividualBasicDetailsImport implements ToModel, WithMappedCells
{
    private $request;

    private $employeeData;

    private IndividualBasicDetailManager $individualBasicDetailService;

    public $importedRecords;

    public function __construct(
        IndividualBasicDetailManager $individualBasicDetailService,
        array $request
    ) {
        $this->individualBasicDetailService = $individualBasicDetailService;
        $this->request = $request;
        $this->employeeData = $this->processEmployeeData($request);
        $this->importedRecords = new Collection();
    }

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
            'first_name' => 'D11',
            'last_name' => 'D10',
            'middle_name' => 'D12',
            'ext_name' => 'L12',
            'birthday' => 'D13',
            //'mobile_number' => 'I33', @todo map to individual contact info
            //'telephone_number' => 'I32', @todo map to individual contact info
            'sex' => 'D16',
            //'email' => 'I34', @todo map to individual contact info
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
        ];
    }

    public function model(array $row)
    {
        // Process data.
        $row['citizenship'] = $row['dual_citizenship'] ? Citizenship::DUAL_CITIZENSHIP->value : Citizenship::FILIPINO->value;
        $row['citizenship_acquisition'] = $row['citizenship_by_naturalization'] ? CitizenshipAcquisition::NATURALIZATION->value : CitizenshipAcquisition::BIRTH->value;
        $row['birthday'] = Date::excelToDateTimeObject($row['birthday'])->format('Y-m-d');

        $restructuredData = [
            'individual' => $row,
            'employee' => $this->employeeData,
        ];
        $createRules = new IndividualBasicDetailRequest();

        $validatedData = Validator::validate($restructuredData, $createRules->getStoreIndividualRules());

        $individualData = $this->individualBasicDetailService->store($validatedData);

        // Push the created record into the collection so that it persists outside of this importer.
        // This allows it to be accessed outside of this importer.
        $this->importedRecords->push($individualData);

        return $individualData;
    }
}
