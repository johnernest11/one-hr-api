<?php

namespace App\Services\ComprehensiveRecords;

use App\Enums\ItemStatus;
use App\Enums\PaginationType;
use App\Imports\MainIndividualImporter;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\Item;
use App\Services\DailyTimeRecords\QrCodeManager;
use App\Traits\Services\CanBuildPagination;
use App\Traits\Services\CanResolveModelFromId;
use App\Traits\Services\PdsPdfBuilder;
use Carbon\Carbon;
use Excel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use Storage;
use TheIconic\NameParser\Parser;

class IndividualBasicDetailService implements IndividualBasicDetailManager
{
    use CanBuildPagination;
    use CanResolveModelFromId;

    public const MAX_TRANSACTION_DEADLOCK_ATTEMPTS = 5;

    // @todo: Update this array for every added models in PDS
    private $comprehensive_records = [
        'individualAddress',
        'individualContactInfo',
        'individualFamily',
        'individualEducationalBackground',
        'individualEligibility',
        'individualWorkExperience',
        'individualVoluntaryWork',
        'individualLnd',
        'individualMembership',
        'individualRecognition',
        'individualSkillsHobby',
        'individualQuestion',
        'individualReference',
        'individualGovernmentId',
    ];

    private IndividualBasicDetail $model;

    private $maxReferences = 3; // Maximum number of references

    protected Parser $nameParser;

    private QrCodeManager $qrCodeService;

    private ?PdsPdfBuilder $pdfBuilder = null;

    public function __construct(IndividualBasicDetail $model, Parser $nameParser, QrCodeManager $qrCodeService)
    {
        $this->model = $model;
        $this->nameParser = $nameParser;
        $this->qrCodeService = $qrCodeService;
    }

    // @todo follow the structure:
    // CRUD for consolidated
    // CRUD for C1
    // CRUD for C2
    // CRUD for C3
    // CRUD for C4
    // CRUD for WES
    // imports & exports

    /** {@inheritDoc} */
    public function all(?int $limit = null): LengthAwarePaginator
    {
        $query = $this->model->filtered();

        return $this->buildPagination(PaginationType::LENGTH_AWARE, $query, $limit);
    }

    /**
     * Fetch the consolidated data of the personnel for PDS
     *{@inheritDoc}
     */
    public function viewConsolidatedData(IndividualBasicDetail|int $individualBasicDetail): IndividualBasicDetail
    {
        // check if IndividualBasicDetail or int
        if ($individualBasicDetail instanceof IndividualBasicDetail) {
            $individualId = $individualBasicDetail->id;
        } else {
            $individualId = $individualBasicDetail;
        }

        $individualData = $this->model->with(array_merge($this->comprehensive_records, ['employee']))->where('id', $individualId)->first();

        return $individualData;

    }

    public function store(array $request): IndividualBasicDetail
    {
        return DB::transaction(function () use ($request) {
            $individualData = $this->model->create($request['individual']);
            // Create employee seperately
            $individualData->employee()->create($request['employee']);

            // Update Items
            // Update status to filled and set date_filled_up as current date
            $individualData->employee->item->update([
                'status' => ItemStatus::FILLED->value,
                'date_filled_up' => Carbon::now(),
            ]);

            $toSkip = [
                'individual',
                'employee',
            ];

            foreach ($request as $relationshipName => $inputData) {
                if (in_array($relationshipName, $toSkip)) {
                    continue; // Skip excluded relations.
                }
                $relationshipName = Str::camel($relationshipName); // convert to camel case to cater to the next portion

                // ← Insert HasOne logic here
                if ($relationshipName === 'individualGovernmentId') {
                    $govData = is_array($inputData) ? $inputData : [];
                    $existingGov = $individualData->individualGovernmentId;

                    if ($existingGov) {
                        $existingGov->update(Arr::except($govData, ['id', '_delete']));
                    } elseif (! empty($govData)) {
                        $individualData->individualGovernmentId()->create(Arr::except($govData, ['id', '_delete']));
                    }

                    continue;
                }
                if ($individualData->{$relationshipName}() instanceof Relation && is_array($inputData) && ! empty($inputData)) {
                    foreach ($inputData as $modelData) {
                        $individualData->{$relationshipName}()->create($modelData);

                    }
                }
            }

            $individualData = $individualData->refresh();

            $fetchedIndividualData = $this->model->with(array_merge($this->comprehensive_records, ['employee']))->where('id', $individualData->id)->first();

            return $fetchedIndividualData;
        }, self::MAX_TRANSACTION_DEADLOCK_ATTEMPTS);

    }

    public function update(IndividualBasicDetail $individualBasicDetail, array $request): IndividualBasicDetail
    {
        // Pass data
        // Update data per module
        // Return same eager load rel.
        // Need id. If ID is not passed, it's a new record

        return DB::transaction(function () use ($individualBasicDetail, $request) {

            if (array_key_exists('individual', $request)) {
                $individualBasicDetail->update($request['individual']);
            }
            if (array_key_exists('employee', $request)) {
                // Check if item_id is changed, if it is, set old item as unfilled
                //   and set new item as filled
                if (isset($request['employee']['item_id'])) {
                    $oldItemId = $individualBasicDetail->employee->item_id;
                    $newItemId = $request['employee']['item_id'];

                    if ($oldItemId != $newItemId) {
                        $oldItem = Item::find($oldItemId);
                        $oldItem->update([
                            'status' => ItemStatus::UNFILLED->value,
                            'date_filled_up' => null,
                        ]);

                        $newItem = Item::find($newItemId);
                        $newItem->update([
                            'status' => ItemStatus::FILLED->value,
                            'date_filled_up' => Carbon::now(),
                        ]);
                    }
                }

                $individualBasicDetail->employee()->update($request['employee']);
                // Check if id_number is changed.
                // if it is, update qr code.
                if (isset($request['employee']['id_number'])) {
                    $updatedEmployee = $individualBasicDetail->employee->fresh();
                    $this->qrCodeService->create($updatedEmployee);
                }
            }

            // For every model,
            // Check if array_key_exists
            // Loop Through the Key and update

            $excludedRel = [
                'individual',
                'employee',
                'form_type', // Skip since its not really a model. It is only for determining which form is currently being updated.
            ];

            // For every model,
            // Loop Through the Key and update
            foreach ($request as $relationshipName => $inputData) {
                if (in_array($relationshipName, $excludedRel)) {
                    continue; // Skip excluded relations.
                }

                $relationshipName = Str::camel($relationshipName); // convert to camel case to cater to the next portion

                if ($individualBasicDetail->{$relationshipName}() instanceof Relation && is_array($inputData) && ! empty($inputData)) {
                    foreach ($inputData as $modelData) {
                        // Extract ID if present, otherwise it's a new record
                        $id = $modelData['id'] ?? null;

                        // Get the class name for dynamic update/create/delete
                        $className = get_class($individualBasicDetail->{$relationshipName}()->getRelated());

                        if ($relationshipName instanceof \Illuminate\Database\Eloquent\Relations\HasOne) {
                            $data = is_array($inputData) ? $inputData[0] : $inputData;
                            if ($relationshipName->exists) {
                                $relationshipName->update($data);
                            } else {
                                $relationshipName->create($data);
                            }
                        }

                        // Soft delete if _delete flag is set
                        if (isset($modelData['_delete']) && $modelData['_delete'] && $id) {
                            $recordToDelete = $className::findOrFail($id); // Check if record exists before deletion
                            $recordToDelete->delete();

                            continue; // Skip to the next iteration
                        }
                        if ($relationshipName === 'individualGovernmentId') {
                            // HasOne: update if exists, otherwise create
                            $govData = is_array($inputData) ? $inputData : [];
                            $existingGov = $individualBasicDetail->individualGovernmentId;

                            if ($existingGov) {
                                $existingGov->update(Arr::except($govData, ['id', '_delete']));
                            } else {
                                $individualBasicDetail->individualGovernmentId()->create(Arr::except($govData, ['id', '_delete']));
                            }

                            continue; // skip the generic HasMany loop
                        }

                        if ($id) {
                            // Update existing record if ID exists
                            $record = $className::findOrFail($id); // Check if record exists
                            $record->update(Arr::except($modelData, ['id', '_delete'])); // Exclude id and _delete
                        } else {
                            // Create new record if ID is not passed
                            $individualBasicDetail->{$relationshipName}()->create($modelData);
                        }

                    }
                }

            }

            $fetchedIndividualData = $this->model->with(array_merge($this->comprehensive_records, ['employee']))->where('id', $individualBasicDetail->id)->first();

            return $fetchedIndividualData;
        }, self::MAX_TRANSACTION_DEADLOCK_ATTEMPTS);
    }

    /**
     * Search individual via last_name, first_name, and middle_name
     */
    public function search(
        string $term,
        ?PaginationType $pagination = null,
        ?int $limit = null
    ): Collection|Paginator|LengthAwarePaginator|CursorPaginator {

        $individual = IndividualBasicDetail::query()
            ->with(array_merge($this->comprehensive_records, ['employee']))
           // Do a full match search for the names as they have a fullText index in our migrations
            ->whereFullText('first_name', $term)
            ->orWhereFullText('last_name', $term)
            ->orWhereFullText('middle_name', $term);

        return $this->buildPagination($pagination, $individual, $limit);
    }

    public function import(array $validatedRequest): array
    {
        $mainImporter = new MainIndividualImporter($validatedRequest, $this->nameParser);
        Excel::import($mainImporter, $validatedRequest['excel_file']);

        // Get mapped data during the import process.
        $generatedRecords = $mainImporter->getImportedRecords();

        $generatedRecordsArray = $generatedRecords ? $generatedRecords->toArray() : []; // @todo or perhaps handle empty records?

        $restructuredData = [
            ...$generatedRecordsArray,
        ];

        return $restructuredData;
    }

    /**
     * Setter to inject a custom PdsPdfBuilder (useful for testing)
     */
    public function setPdfBuilder(PdsPdfBuilder $builder): void
    {
        $this->pdfBuilder = $builder;
    }

    /**
     * Generate PDS PDF for given individual
     *
     * @param  IndividualBasicDetail|int  $individual
     * @return array ['fileContent' => string, 'fileName' => string]
     */
    /** {@inheritDoc} */
    public function generatePDF(IndividualBasicDetail $individualBasicDetail): array
    {
        // Use the injected builder if available, otherwise create a new one
        $builder = $this->pdfBuilder ?? new PdsPdfBuilder;
        // Page 1 — C1
        $builder->loadTemplate(storage_path('assets/PDS_C1_Template.png'))
            ->renderPersonalInfo($individualBasicDetail)
            ->renderAddress($individualBasicDetail)
            ->renderIds($individualBasicDetail)
            ->renderContact($individualBasicDetail)
            ->renderPhysicalInfo($individualBasicDetail);

        // Page 2 — C2
        $builder->loadTemplate(storage_path('assets/PDS_C2_Template.png'));

        // Page 3 — C3
        $builder->loadTemplate(storage_path('assets/PDS_C3_Template.png'));

        // Page 4 — C4
        $builder->loadTemplate(storage_path('assets/PDS_C4_Template.png'));

        return [
            'fileContent' => $builder->output(),
            'fileName' => "PDS-{$individualBasicDetail->id}.pdf",
        ];
    }

    /**
     * Generate WES Docx for given individual
     */
    public function generateWES(IndividualBasicDetail $individualBasicDetail): array
    {
        $templatePath = Storage::disk('assets')->path('TEMPLATE - Work Experience Sheet.docx');
        $templateProcessor = new TemplateProcessor($templatePath);
        $sanitize = fn ($val) => htmlspecialchars((string) ($val ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');

        // Local Helper for Date Formatting
        $formatDur = function ($from, $to, $isCurrent) {
            if (! $from) {
                return 'N/A';
            }
            $start = \Carbon\Carbon::parse($from)->format('M d, Y');

            return ($isCurrent || ! $to || $to === '1970-01-01') ? "$start to Present" : "$start to ".\Carbon\Carbon::parse($to)->format('M d, Y');
        };

        // Map Work Experience Rows
        $workRows = $individualBasicDetail->individualWorkExperience->map(fn ($work) => [
            'duration' => $sanitize($formatDur($work->inclusive_date_from, $work->inclusive_date_to, $work->is_current_work)),
            'position' => $sanitize($work->position_title),
            'agencyOrganization' => $sanitize($work->department_agency_office_company),
            'officeUnit' => $sanitize($work->office_unit ?? 'N/A'),
            'immediateSupervisor' => $sanitize($work->immediate_supervisor ?? 'N/A'),
            'accomplishmentContribution' => $sanitize($work->significant_accomplishments ?? 'N/A'),
            'summaryDuties' => $sanitize($work->summary_of_actual_duties ?? 'N/A'),
        ])->toArray();

        // Clone Rows or Set Fallbacks
        if (! empty($workRows)) {
            $templateProcessor->cloneRowAndSetValues('duration', $workRows);
        } else {
            $templateProcessor->setValues(['duration' => 'N/A', 'position' => 'N/A', 'agencyOrganization' => 'N/A']);
        }

        // Static Info
        $templateProcessor->setValues([
            'fullName' => $sanitize(trim(
                "{$individualBasicDetail->first_name} ".
                ($individualBasicDetail->middle_name ? strtoupper($individualBasicDetail->middle_name[0]).'. ' : '').
                "{$individualBasicDetail->last_name} ".
                ($individualBasicDetail->ext_name?->value ?? '')
            )),

            'date' => date('F d, Y'),
        ]);

        ob_start();
        $templateProcessor->saveAs('php://output');

        return [
            'fileContent' => ob_get_clean(),
            'fileName' => "{$individualBasicDetail->id}-{$individualBasicDetail->last_name}-WES.docx",
        ];
    }
}
