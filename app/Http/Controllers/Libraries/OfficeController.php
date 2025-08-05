<?php

namespace App\Http\Controllers\Libraries;

use App\Enums\PaginationType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Libraries\OfficeRequest;
use App\Models\Libraries\Office;
use App\Traits\Services\CanBuildPagination;
use Illuminate\Http\JsonResponse;
use PaginationHelper;
use Symfony\Component\HttpFoundation\Response;

class OfficeController extends ApiController
{
    use CanBuildPagination;

    /**
     * Retrieve all offices
     */
    public function fetch(OfficeRequest $request): JsonResponse
    {
        $query = Office::query()->orderBy('name');
        $offices = $this->buildPagination(PaginationType::LENGTH_AWARE, $query);

        $offices_formatted = PaginationHelper::formatPagination($offices);

        return $this->success($offices_formatted, Response::HTTP_OK);

    }

    /**
     * Search for an office via name
     */
    public function search(OfficeRequest $request): JsonResponse
    {
        $q = $request->validated()['query'];
        $limit = $request->validated('limit', 9);

        $offices = Office::where('name', 'like', "%$q%")->paginate($limit)->toArray();

        $offices_formatted = PaginationHelper::formatLengthAwarePagination($offices);

        return $this->success($offices_formatted, Response::HTTP_OK);
    }
}
