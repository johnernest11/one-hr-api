<?php

namespace App\Http\Controllers\Libraries;

use App\Enums\PaginationType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Libraries\FundSourceRequest;
use App\Models\Libraries\FundSource;
use App\Traits\Services\CanBuildPagination;
use Illuminate\Http\JsonResponse;
use PaginationHelper;
use Symfony\Component\HttpFoundation\Response;

class FundSourceController extends ApiController
{
    use CanBuildPagination;

    /**
     * Retrieve all Fund Sources
     */
    public function fetch(FundSourceRequest $request): JsonResponse
    {
        $query = FundSource::query()->orderBy('name');
        $funds = $this->buildPagination(PaginationType::LENGTH_AWARE, $query);

        $funds_formatted = PaginationHelper::formatPagination($funds);

        return $this->success($funds_formatted, Response::HTTP_OK);

    }

    /**
     * Search for a fund sources via title or parenthetical_title
     */
    public function search(FundSourceRequest $request): JsonResponse
    {
        $q = $request->validated()['query'];
        $limit = $request->validated('limit', 9);

        $funds = FundSource::where('name', 'like', "%$q%")->paginate($limit)->toArray();

        $funds_formatted = PaginationHelper::formatLengthAwarePagination($funds);

        return $this->success($funds_formatted, Response::HTTP_OK);
    }
}
