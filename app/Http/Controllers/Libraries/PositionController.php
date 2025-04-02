<?php

namespace App\Http\Controllers\Libraries;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Libraries\PositionRequest;
use App\Models\Libraries\Position;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PositionController extends ApiController
{
    /**
     * Retrieve all positions
     */
    public function fetch(PositionRequest $request): JsonResponse
    {
        $positions = Position::filtered()->orderBy('title')->get();

        return $this->success(['data' => $positions], Response::HTTP_OK);
    }
}
