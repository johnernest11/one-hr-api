<?php

namespace App\Services\ComprehensiveRecords;

use App\Enums\PaginationType;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Traits\Services\CanBuildPagination;
use App\Traits\Services\CanResolveModelFromId;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    public function all(?int $limit = null): LengthAwarePaginator
    {
        $query = $this->model->with(array_merge($this->comprehensive_records, ['employee']));

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

            $toSkip = [
                'individual',
                'employee',
            ];

            foreach ($request as $relationshipName => $inputData) {
                if (in_array($relationshipName, $toSkip)) {
                    continue; // Skip excluded relations.
                }
                $relationshipName = Str::camel($relationshipName); // convert to camel case to cater to the next portion

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
                $individualBasicDetail->employee()->update($request['employee']);
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
}
