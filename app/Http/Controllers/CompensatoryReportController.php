<?php

namespace App\Http\Controllers;

use App\Models\compensatoryReport;
use App\Services\CompensatoryReport\CompensatoryReportManager;
use PaginationHelper;
use Symfony\Component\HttpFoundation\Response;

class CompensatoryReportController extends ApiController
{
    private CompensatoryReportManager $compensatoryReportService;

    public function __construct(CompensatoryReportManager $cTDOService)
    {
        $this->compensatoryReportService = $cTDOService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $reports = $this->compensatoryReportService->all();
        $formatted = PaginationHelper::formatPagination($reports);

        return $this->success($formatted, Response::HTTP_OK);

    }

    /**
     * Display the specified resource.
     */
    public function show(compensatoryReport $compensatoryReport)
    {
        $this->authorize('view', $compensatoryReport);
        $report = $this->compensatoryReportService->read($compensatoryReport);

        return $this->success(['data' => $report], Response::HTTP_OK);
    }
}
