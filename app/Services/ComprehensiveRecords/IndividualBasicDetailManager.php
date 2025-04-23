<?php

namespace App\Services\ComprehensiveRecords;

use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
}
