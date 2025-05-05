<?php

namespace App\Http\Controllers\Libraries;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Libraries\SalaryGradeRequest;
use App\Models\Libraries\SalaryGrade;
use Illuminate\Http\JsonResponse;
use PaginationHelper;
use Symfony\Component\HttpFoundation\Response;

class SalaryGradeController extends ApiController
{
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
        $salaryGrades = SalaryGrade::filtered()->orderBy('salary_grade')->paginate(9)->toArray();

        $sg_formatted = PaginationHelper::formatLengthAwarePagination($salaryGrades);

        return $this->success($sg_formatted, Response::HTTP_OK);

    }

    /**
     * Search for a salary grade via salary grade
     */
    public function search(SalaryGradeRequest $request): JsonResponse
    {
        $q = $request->validated()['query'];
        $limit = $request->validated('limit', 9);

        // Match query to the following fields:
        // salary_grade
        // step
        // tranche
        // effective_date
        $salaryGrades = SalaryGrade::where('salary_grade', 'like', "%$q%")
            ->orWhere('step', 'like', "%$q%")
            ->orWhere('tranche', 'like', "%$q%")
            ->orWhere('effective_date', 'like', "%$q%")
            ->where('active', '=', '1')->paginate($limit)->toArray();

        $sg_formatted = PaginationHelper::formatLengthAwarePagination($salaryGrades);

        return $this->success($sg_formatted, Response::HTTP_OK);
    }
}
