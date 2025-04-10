<?php

namespace App\Http\Controllers\Libraries;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Libraries\PositionRequest;
use App\Models\Libraries\Position;
use Illuminate\Http\JsonResponse;
use PaginationHelper;
use Symfony\Component\HttpFoundation\Response;

class PositionController extends ApiController
{
    /**
     * Retrieve all positions
     */
    public function fetch(PositionRequest $request): JsonResponse
    {
        // Paginate 9 per page
        $positions = Position::filtered()->orderBy('title')->paginate(9)->toArray();

        $positions_formatted = PaginationHelper::formatLengthAwarePagination($positions);

        return $this->success($positions_formatted, Response::HTTP_OK);
    }

    /**
     * Search for a position via title or parenthetical_title
     */
    public function search(PositionRequest $request): JsonResponse
    {
        $q = $request->validated(['query']);
        $perPage = $request->validated('per-page', 9);

        $positions = Position::where('title', 'like', "%$q%")->orWhere('parenthetical_title', 'like', "%$q%")->paginate($perPage)->toArray();

        $positions_formatted = PaginationHelper::formatLengthAwarePagination($positions);

        return $this->success($positions_formatted, Response::HTTP_OK);
    }
}
