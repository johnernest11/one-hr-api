<?php

namespace App\Http\Controllers\Libraries;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Libraries\OfficeRequest;
use App\Models\Libraries\Office;
use Illuminate\Http\JsonResponse;
use PaginationHelper;
use Symfony\Component\HttpFoundation\Response;

class OfficeController extends ApiController
{
    /**
     * Retrieve all offices
     */
    public function fetch(OfficeRequest $request): JsonResponse
    {
        $offices = Office::orderBy('name')->paginate(9)->toArray();

        $offices_formatted = PaginationHelper::formatLengthAwarePagination($offices);

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
