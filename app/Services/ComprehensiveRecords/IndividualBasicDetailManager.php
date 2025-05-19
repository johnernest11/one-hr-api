<?php

namespace App\Services\ComprehensiveRecords;

use App\Enums\PaginationType;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\CursorPaginator;

interface IndividualBasicDetailManager
{
    /**
     * Fetch all IndividualBasicDetails
     */
    public function all(?int $limit = null): LengthAwarePaginator;

    /**
     * Create an IndividualBasicDetail
     */
    public function store(array $request): IndividualBasicDetail;

    /**
     * Fetch a single IndividualBasicDetail
     */
    public function viewConsolidatedData(IndividualBasicDetail|int $individualBasicDetail): IndividualBasicDetail;

    /**
     * Update an IndividualBasicDetail
     */
    public function update(IndividualBasicDetail $individualBasicDetail, array $request): IndividualBasicDetail;

    /**
     * Check if an individual reaches the maximum number of references
     */
    public function isMaximumReferences(IndividualBasicDetail $individualBasicDetail, array $request): bool;

    /**
     * Search for IndividualBasicDetail
     */
    public function search(
        string $term,
        ?PaginationType $pagination = null,
        ?int $limit = null
    ): Collection|Paginator|LengthAwarePaginator|CursorPaginator;
}
