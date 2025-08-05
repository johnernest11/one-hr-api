<?php

namespace App\Http\Controllers\Libraries;

use App\Enums\PaginationType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Libraries\SectionOrUnitRequest;
use App\Models\Libraries\SectionOrUnit;
use App\Traits\Services\CanBuildPagination;
use Illuminate\Http\JsonResponse;
use PaginationHelper;
use Symfony\Component\HttpFoundation\Response;

class SectionOrUnitController extends ApiController
{
    use CanBuildPagination;

    /**
     * Retrieve all Sections or Units
     */
    public function fetch(SectionOrUnitRequest $request): JsonResponse
    {
        $query = SectionOrUnit::query()->orderBy('name')->with('divisions');
        $section_units = $this->buildPagination(PaginationType::LENGTH_AWARE, $query);

        $section_units_formatted = PaginationHelper::formatPagination($section_units);

        return $this->success($section_units_formatted, Response::HTTP_OK);

    }

    /**
     * Search for a section or unit via name
     */
    public function search(SectionOrUnitRequest $request): JsonResponse
    {
        $q = $request->validated()['query'];
        $limit = $request->validated('limit', 9);

        $section_units = SectionOrUnit::where('name', 'like', "%$q%")->with('divisions')->paginate($limit)->toArray();

        $section_units_formatted = PaginationHelper::formatLengthAwarePagination($section_units);

        return $this->success($section_units_formatted, Response::HTTP_OK);
    }
}
