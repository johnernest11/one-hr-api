<?php

namespace App\Http\Controllers\ComprehensiveRecords;

use App\Enums\ApiErrorCode;
use App\Enums\PaginationType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\ComprehensiveRecords\IndividualBasicDetailRequest;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Services\ComprehensiveRecords\IndividualBasicDetailManager;
use Illuminate\Http\JsonResponse;
use PaginationHelper;
use Symfony\Component\HttpFoundation\Response;

class IndividualBasicDetailController extends ApiController
{
    private IndividualBasicDetailManager $individualBasicDetailService;

    public function __construct(
        IndividualBasicDetailManager $individualBasicDetailService,
    ) {
        $this->individualBasicDetailService = $individualBasicDetailService;
    }

    /**
     * View all Individual Data
     */
    public function viewAllIndividuals(IndividualBasicDetailRequest $request): JsonResponse
    {
        $limit = $request->validated('limit', 9);

        $individualData = $this->individualBasicDetailService->all($limit);
        $formatted = PaginationHelper::formatPagination($individualData);

        return $this->success($formatted, Response::HTTP_OK);
    }

    /**
     * View Individual Data by ID
     */
    public function viewSpecificIndividual(IndividualBasicDetail $individualBasicDetail, IndividualBasicDetailRequest $request): JsonResponse
    {

        $individualData = $this->individualBasicDetailService->viewConsolidatedData($individualBasicDetail);

        return $this->success(['data' => $individualData], Response::HTTP_OK);
    }

    public function store(IndividualBasicDetailRequest $request): JsonResponse
    {
        $individualData = $this->individualBasicDetailService->store($request->validated());

        return $this->success(['data' => $individualData], Response::HTTP_CREATED);
    }

    public function update(IndividualBasicDetail $individualBasicDetail, IndividualBasicDetailRequest $request): JsonResponse
    {
        // Validate request.
        $validatedRequest = $request->validated();
        // Check if IndividualReference exists on the request
        if (array_key_exists('individual_reference', $validatedRequest)) {
            // Check if maximum reference is reached for user.
            // If it is, throw error.
            if ($this->individualBasicDetailService->isMaximumReferences($individualBasicDetail, $validatedRequest)) {
                return $this->error('Maximum number of references reached for this individual.', Response::HTTP_BAD_REQUEST, ApiErrorCode::BAD_REQUEST);
            }
        }
        $individualData = $this->individualBasicDetailService->update($individualBasicDetail, $validatedRequest);

        return $this->success(['data' => $individualData], Response::HTTP_OK);

    }

    public function search(IndividualBasicDetailRequest $request): JsonResponse
    {
        $q = $request->validated()['query'];
        $limit = $request->validated('limit', 9);

        $individualData = $this->individualBasicDetailService->search($q, PaginationType::LENGTH_AWARE, $limit);
        $formatted = PaginationHelper::formatPagination($individualData);

        return $this->success($formatted, Response::HTTP_OK);

    }
}
