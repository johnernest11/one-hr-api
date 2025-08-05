<?php

namespace App\Http\Controllers\Libraries;

use App\Enums\PaginationType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Libraries\PositionRequest;
use App\Models\Libraries\Position;
use App\Traits\Services\CanBuildPagination;
use Illuminate\Http\JsonResponse;
use PaginationHelper;
use Symfony\Component\HttpFoundation\Response;

class PositionController extends ApiController
{
    use CanBuildPagination;

    /**
     * Retrieve all positions
     */
    public function fetch(PositionRequest $request): JsonResponse
    {
        $query = Position::query()->filtered()->orderBy('title');
        $positions = $this->buildPagination(PaginationType::LENGTH_AWARE, $query);

        $positions_formatted = PaginationHelper::formatPagination($positions);

        return $this->success($positions_formatted, Response::HTTP_OK);
    }

    /**
     * Search for a position via title or parenthetical_title
     */
    public function search(PositionRequest $request): JsonResponse
    {
        $q = $request->validated(['query']);
        $limit = $request->validated('limit', 9);

        $positions = Position::where('title', 'like', "%$q%")->orWhere('parenthetical_title', 'like', "%$q%")->paginate($limit)->toArray();

        $positions_formatted = PaginationHelper::formatLengthAwarePagination($positions);

        return $this->success($positions_formatted, Response::HTTP_OK);
    }
}
