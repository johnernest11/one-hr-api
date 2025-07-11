<?php

namespace App\Imports;

use App\Services\ComprehensiveRecords\IndividualBasicDetailManager;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Handle mapping of sheets to each import object
 */
class MainIndividualImporter implements WithMultipleSheets
{
    private $request;

    private IndividualBasicDetailManager $individualBasicDetailService;

    public $allImportedRecords; //@todo Can be altered later on depending on which logic is best in accessing the generated records.

    protected $sheetImporters = []; // Save the initialized importers

    public function __construct(
        IndividualBasicDetailManager $individualBasicDetailService,
        array $request
    ) {
        $this->individualBasicDetailService = $individualBasicDetailService;
        $this->request = $request;
        $this->allImportedRecords = new Collection();
        // Initialize the importers here so that they are initialized only once.
        // This is to preserve the generated records per importer.
        $this->sheetImporters['C1'] = new IndividualBasicDetailsImport($this->individualBasicDetailService, $this->request);
    }

    public function sheets(): array
    {
        return $this->sheetImporters;
    }

    /**
     * Iterate through the instantiated sheet importers and collects their imported records.
     */
    public function getImportedRecords(): Collection
    {
        // @todo subject to changes. This logic might not be the best way to handle this.
        $allImportedRecords = new Collection();

        // Iterate through the *stored instances* to collect their data
        foreach ($this->sheetImporters as $sheetName => $sheetImporter) {
            if (property_exists($sheetImporter, 'importedRecords') && $sheetImporter->importedRecords instanceof Collection) {
                $allImportedRecords = $allImportedRecords->merge($sheetImporter->importedRecords);
            }
        }

        return $allImportedRecords;
    }
}
