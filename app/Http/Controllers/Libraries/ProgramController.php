<?php

namespace App\Http\Controllers\Libraries;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Libraries\ProgramRequest;
use App\Models\Libraries\Program;
use Illuminate\Http\JsonResponse;
use PaginationHelper;
use Symfony\Component\HttpFoundation\Response;

class ProgramController extends ApiController
{
    /**
     * Retrieve all programs
     */
    public function fetch(ProgramRequest $request): JsonResponse
    {
        $programs = Program::orderBy('name')->paginate(9)->toArray();

        $programs_formatted = PaginationHelper::formatLengthAwarePagination($programs);

        return $this->success($programs_formatted, Response::HTTP_OK);

    }

    /**
     * Search for a program via name
     */
    public function search(ProgramRequest $request): JsonResponse
    {
        $q = $request->validated()['query'];
        $limit = $request->validated('limit', 9);

        $programs = Program::where('name', 'like', "%$q%")->paginate($limit)->toArray();

        $programs_formatted = PaginationHelper::formatLengthAwarePagination($programs);

        return $this->success($programs_formatted, Response::HTTP_OK);
    }
}
