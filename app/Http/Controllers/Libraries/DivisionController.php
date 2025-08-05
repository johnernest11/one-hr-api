<?php

namespace App\Http\Controllers\Libraries;

use App\Enums\PaginationType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Libraries\DivisionRequest;
use App\Models\Libraries\Division;
use App\Traits\Services\CanBuildPagination;
use Illuminate\Http\JsonResponse;
use PaginationHelper;
use Symfony\Component\HttpFoundation\Response;

class DivisionController extends ApiController
{
    use CanBuildPagination;

    /**
     * Retrieve all divisions
     */
    public function fetch(DivisionRequest $request): JsonResponse
    {
        $query = Division::query()->orderBy('name')->with('sectionOrUnits');
        $divisions = $this->buildPagination(PaginationType::LENGTH_AWARE, $query);

        $divisions_formatted = PaginationHelper::formatPagination($divisions);

        return $this->success($divisions_formatted, Response::HTTP_OK);

    }

    /**
     * Search for a division via name
     */
    public function search(DivisionRequest $request): JsonResponse
    {
        $q = $request->validated()['query'];
        $limit = $request->validated('limit', 9);

        $divisions = Division::where('name', 'like', "%$q%")->with('sectionOrUnits')->paginate($limit)->toArray();

        $divisions_formatted = PaginationHelper::formatLengthAwarePagination($divisions);

        return $this->success($divisions_formatted, Response::HTTP_OK);
    }
}
