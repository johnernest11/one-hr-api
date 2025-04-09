<?php

namespace App\Http\Controllers\Libraries;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Libraries\FundSourceRequest;
use App\Models\Libraries\FundSource;
use Illuminate\Http\JsonResponse;
use PaginationHelper;
use Symfony\Component\HttpFoundation\Response;

class FundSourceController extends ApiController
{
    /**
     * Retrieve all Fund Sources
     */
    public function fetch(FundSourceRequest $request): JsonResponse
    {
        $funds = FundSource::orderBy('name')->paginate(9)->toArray();

        $funds_formatted = PaginationHelper::formatLengthAwarePagination($funds);

        return $this->success($funds_formatted, Response::HTTP_OK);

    }

    /**
     * Search for a fund sources via title or parenthetical_title
     */
    public function search(FundSourceRequest $request): JsonResponse
    {
        $q = $request->validated()['query'];
        // Paginate 9 per page
        $funds = FundSource::where('name', 'like', "%$q%")->paginate(9)->toArray();

        $funds_formatted = PaginationHelper::formatLengthAwarePagination($funds);

        return $this->success($funds_formatted, Response::HTTP_OK);
    }
}
