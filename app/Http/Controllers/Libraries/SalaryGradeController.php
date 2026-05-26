<?php

namespace App\Http\Controllers\Libraries;

use App\Enums\PaginationType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Libraries\SalaryGradeRequest;
use App\Models\Libraries\SalaryGrade;
use App\Traits\Services\CanBuildPagination;
use Illuminate\Http\JsonResponse;
use PaginationHelper;
use Symfony\Component\HttpFoundation\Response;

class SalaryGradeController extends ApiController
{
    use CanBuildPagination;

    /**
     * Retrieve all Salary Grades. Can also filter based on the following:
     * 1. nbc-no,
     * 2. effective-date,
     * 3. tranche,
     * 4. sg,
     * 5. step,
     * 6. active.
     */
    public function fetch(SalaryGradeRequest $request): JsonResponse
    {
        $query = SalaryGrade::query()->filtered()->orderBy('salary_grade');
        $salaryGrades = $this->buildPagination(PaginationType::LENGTH_AWARE, $query);

        $sg_formatted = PaginationHelper::formatPagination($salaryGrades);

        return $this->success($sg_formatted, Response::HTTP_OK);

    }

    /**
     * Search for a salary grade via salary grade
     */
    public function search(SalaryGradeRequest $request): JsonResponse
    {
        $q = $request->validated()['query'];
        $limit = $request->validated('limit', 100);

        // Match query to the salary_grade fields:
        $salaryGrades = SalaryGrade::where('salary_grade', '=', "$q")
            ->where('active', '=', '1')->orderBy('effective_date', 'asc')->paginate($limit)->toArray();

        $sg_formatted = PaginationHelper::formatLengthAwarePagination($salaryGrades);

        return $this->success($sg_formatted, Response::HTTP_OK);
    }
}
