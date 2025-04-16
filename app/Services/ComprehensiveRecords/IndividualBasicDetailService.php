<?php

namespace App\Services\ComprehensiveRecords;

use App\Enums\PaginationType;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Traits\Services\CanBuildPagination;
use App\Traits\Services\CanResolveModelFromId;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class IndividualBasicDetailService
{
    // @todo: Add Manager
    use CanBuildPagination;
    use CanResolveModelFromId;

    public const MAX_TRANSACTION_DEADLOCK_ATTEMPTS = 5;

    // @todo: Update this array for every added models in PDS
    private $comprehensive_records = [
        'individualAddress',
        'individualContactInfo',
    ];

    // @todo Update this array until all C1 models are added
    private $c1_records = [
        'individualAddress',
        'individualContactInfo',
    ];

    private IndividualBasicDetail $model;

    public function __construct(IndividualBasicDetail $model)
    {
        $this->model = $model;

    }

    //@todo follow the structure:
    //CRUD for consolidated
    //CRUD for C1
    //CRUD for C2
    //CRUD for C3
    //CRUD for C4
    //CRUD for WES
    //imports & exports

    public function all(): LengthAwarePaginator
    {
        /** @var Builder $item */
        $query = $this->model->with(array_merge($this->comprehensive_records, ['employee']));

        return $this->buildPagination(PaginationType::LENGTH_AWARE, $query);
    }

    /**
     * Fetch the consolidated data of the personnel for PDS
     *{@inheritDoc}
     */
    public function viewConsolidatedData(IndividualBasicDetail $individualBasicDetail): IndividualBasicDetail
    {
        $individualId = $individualBasicDetail->id;

        $individualData = $this->model->with(array_merge($this->comprehensive_records, ['employee']))->where('id', $individualId)->first();

        return $individualData;

    }

    public function store(array $request)
    {
        return DB::transaction(function () use ($request) {
            $individualData = $this->model->create($request['individual']);

            $forUpdateRel = [
                'individual',
            ];

            foreach ($request as $relationshipName => $inputData) {
                if (in_array($relationshipName, $forUpdateRel)) {
                    continue; // Skip excluded relations. They need to be updated.
                }

                if ($individualData->{$relationshipName}() instanceof Relation && is_array($inputData) && ! empty($inputData)) {
                    $individualData->{$relationshipName}()->create($inputData[0]);
                }
            }

            $individualData = $individualData->refresh();

            $fetchedIndividualData = $this->model->with(array_merge($this->comprehensive_records, ['employee']))->where('id', $individualData->id)->first();

            return $fetchedIndividualData;
        }, self::MAX_TRANSACTION_DEADLOCK_ATTEMPTS);

    }

    public function update(IndividualBasicDetail $individualBasicDetail, array $request)
    {
        // Pass data
        // Update data per module
        // Return same eager load rel.
        // Need id. If ID is not passed, it's a new record

        return DB::transaction(function () use ($individualBasicDetail, $request) {

            if ($request['individual']) {
                $individualBasicDetail->update($request['individual']);
            }

            // For every model,
            // Check if array_key_exists
            // Loop Through the Key and update

            $excludedRel = [
                'individual',
            ];

            // For every model,
            // Loop Through the Key and update
            foreach ($request as $relationshipName => $inputData) {
                if (in_array($relationshipName, $excludedRel)) {
                    continue; // Skip excluded relations.
                }

                if ($individualBasicDetail->{$relationshipName}() instanceof Relation && is_array($inputData) && ! empty($inputData)) {
                    foreach ($inputData as $modelData) {
                        // Extract ID if present, otherwise it's a new record
                        $id = $modelData['id'] ?? null;

                        // Get the class name for dynamic update/create/delete
                        $className = get_class($individualBasicDetail->{$relationshipName}()->getRelated());

                        // Soft delete if _delete flag is set
                        if (isset($modelData['_delete']) && $modelData['_delete'] && $id) {
                            $recordToDelete = $className::findOrFail($id); // Check if record exists before deletion
                            $recordToDelete->delete();

                            continue; // Skip to the next iteration
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
}
