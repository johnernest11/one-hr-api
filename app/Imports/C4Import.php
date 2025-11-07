<?php

namespace App\Imports;

use App\Models\Libraries\Country;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMappedCells;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class C4Import implements ToCollection, WithMappedCells
{
    public $importedRecords;

    public function __construct()
    {
        $this->importedRecords = new Collection;
    }

    public function mapping(): array
    {
        return [
            /* -------------------------------------------------------------------------- */
            /*                             IndividualQuestion */
            /* -------------------------------------------------------------------------- */
            'individual_question' => [
                [
                    /* ------------------------------- Question 34 ------------------------------ */
                    'q34_a' => 'N6',
                    'q34_b' => 'N8',
                    'q34_details' => 'H11',
                    /* ------------------------------- Question 35 ------------------------------ */
                    'q35_a' => 'N13',
                    'q35_a_details' => 'H15',
                    'q35_b' => 'N18',
                    'q35_b_date_filed' => 'K20',
                    'q35_b_status' => 'K21',
                    /* ------------------------------- Question 36 ------------------------------ */
                    'q36' => 'N23',
                    'q36_details' => 'H25',
                    /* ------------------------------- Question 37 ------------------------------ */
                    'q37' => 'N27',
                    'q37_details' => 'H29',
                    /* ------------------------------- Question 38 ------------------------------ */
                    'q38_a' => 'N31',
                    'q38_a_details' => 'K32',
                    'q38_b' => 'N34',
                    'q38_b_details' => 'K35',
                    /* ------------------------------- Question 39 ------------------------------ */
                    'q39' => 'N37',
                    'country' => 'H39',
                    /* ------------------------------- Question 40 ------------------------------ */
                    'q40_a_indigenous_group' => 'N43',
                    'q40_a_details' => 'L44',
                    'q40_b_pwd' => 'N45',
                    'q40_b_details' => 'L46',
                    'q40_c_solo_parent' => 'N47',
                    'q40_c_details' => 'L48',
                ],
            ],
            /* -------------------------------------------------------------------------- */
            /*                             IndividualReference */
            /* -------------------------------------------------------------------------- */
            'individual_reference' => [
                [
                    'name' => 'A52',
                    'address' => 'F52',
                    'tel_no' => 'G52',
                ],
                [
                    'name' => 'A53',
                    'address' => 'F53',
                    'tel_no' => 'G53',
                ],
                [
                    'name' => 'A54',
                    'address' => 'F54',
                    'tel_no' => 'G54',
                ],
            ],

            /* -------------------------------------------------------------------------- */
            /*    @todo Mapped Government ID for now. This has no migration/model yet. */
            /* -------------------------------------------------------------------------- */
            'individual_government_id' => [
                'gov_issued_id' => 'D61',
                'gov_id_no' => 'D62',
                'gov_issuance' => 'D64',
            ],
        ];
    }

    public function collection(Collection $rows)
    {
        $rows['individual_question'] = collect($rows['individual_question'])->transform(function ($item) {
            $formattedDate = Date::excelToDateTimeObject($item['q35_b_date_filed'])->format('Y-m-d');
            $item['q35_b_date_filed'] = $formattedDate;

            $countryName = $item['country'];
            $fetchCountry = Country::where('common_name', 'like', "%$countryName%")->orWhere('official_name', 'like', "%$countryName%")->first();
            $item['country_id'] = $fetchCountry ? $fetchCountry->id : null;
            $item['country_official_name'] = $fetchCountry ? $fetchCountry->official_name : null;

            return $item;
        });

        /* -------------------------------------------------------------------------- */
        /*                              Return Collection */
        /* -------------------------------------------------------------------------- */
        // Push the mapped data into the collection so that it persists outside of this importer.
        // This allows it to be accessed outside of this importer.
        $this->importedRecords = $this->importedRecords->merge($rows);

    }
}
