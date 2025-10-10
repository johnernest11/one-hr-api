<?php

namespace App\Http\Controllers;

use App\Enums\ApiErrorCode;
use App\Http\Requests\LocatorSlips\LocatorSlipRequest;
use App\Models\ComprehensiveRecords\Employee;
use App\Models\LocatorSlip\LocatorSlip;
use App\Services\LocatorSlips\LocatorSlipManager;
use Exception;
use Illuminate\Http\JsonResponse;
use PaginationHelper;
use Symfony\Component\HttpFoundation\Response;

class LocatorSlipController extends ApiController
{
    private LocatorSlipManager $locatorSlipService;

    public function __construct(LocatorSlipManager $locatorSlipService)
    {
        $this->locatorSlipService = $locatorSlipService;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Employee $employee, LocatorSlipRequest $request): JsonResponse
    {
        try {
            $ls = $this->locatorSlipService->create($employee, $request->validated());
        } catch (Exception $e) {
            return $this->error(
                $e->getMessage(),
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::BAD_REQUEST
            );
        }

        return $this->success(['data' => $ls], Response::HTTP_CREATED);
    }

    /**
     * Display the employee's locator slips
     */
    public function viewEmployeeLocator(Employee $employee): JsonResponse
    {
        $ls = $this->locatorSlipService->viewEmployeeLocator($employee);
        $formatted = PaginationHelper::formatPagination($ls);

        return $this->success($formatted, Response::HTTP_OK);
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee, LocatorSlip $locatorSlip): JsonResponse
    {
        $ls = $this->locatorSlipService->read($locatorSlip);

        return $this->success(['data' => $ls], Response::HTTP_OK);
    }

    /**
     * Display grouped locator slips by employee
     */
    public function readGrouped(): JsonResponse
    {
        $ls = $this->locatorSlipService->readGrouped();
        $formatted = PaginationHelper::formatPagination($ls);

        return $this->success($formatted, Response::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(LocatorSlip $locatorSlip, LocatorSlipRequest $request): JsonResponse
    {
        $updatedItem = $this->locatorSlipService->update($locatorSlip, $request->validated());

        return $this->success(['data' => $updatedItem], Response::HTTP_OK);

    }
}
