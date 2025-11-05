<?php

namespace App\Http\Controllers;

use App\Enums\PaginationType;
use App\Http\Requests\AccomplishmentReportRequest;
use App\Models\AccomplishmentReport;
use App\Models\User;
use App\Services\AccomplishmentReport\AccomplishmentReportManager;
use PaginationHelper;
use Symfony\Component\HttpFoundation\Response;

class AccomplishmentReportController extends ApiController
{
    private AccomplishmentReportManager $accomplishmentReportService;

    public function __construct(AccomplishmentReportManager $aRService)
    {
        $this->accomplishmentReportService = $aRService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $reports = $this->accomplishmentReportService->all();
        $formatted = PaginationHelper::formatPagination($reports);

        return $this->success($formatted, Response::HTTP_OK);

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AccomplishmentReportRequest $request)
    {
        // Create Accomplishment Report
        $user = User::find(auth()->user()->id);
        $report = $this->accomplishmentReportService->create($user, $request->validated());

        return $this->success(['data' => $report], Response::HTTP_CREATED);

    }

    /**
     * Display the specified resource.
     */
    public function show(AccomplishmentReport $accomplishmentReport)
    {
        $this->authorize('view', $accomplishmentReport);
        $report = $this->accomplishmentReportService->read($accomplishmentReport);

        return $this->success(['data' => $report], Response::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AccomplishmentReportRequest $request, AccomplishmentReport $accomplishmentReport)
    {
        $this->authorize('update', $accomplishmentReport);
        $updatedReport = $this->accomplishmentReportService->update($accomplishmentReport, $request->validated());

        return $this->success(['data' => $updatedReport], Response::HTTP_OK);

    }

    /**
     * Search for a resource in storage.
     */
    public function search(AccomplishmentReportRequest $request)
    {
        $validated = $request->validated();

        $query = $validated['query'] ?? null;
        $limit = $validated['limit'] ?? 5;
        $page = $validated['page'] ?? 1;

        $items = $this->accomplishmentReportService->search($query, PaginationType::LENGTH_AWARE, $limit, $page);
        $formatted = PaginationHelper::formatPagination($items);

        return $this->success($formatted, Response::HTTP_OK);

    }

    public function generateAccomplishmentReport(AccomplishmentReport $accomplishmentReport)
    {
        $response = $this->accomplishmentReportService->generate($accomplishmentReport);

        return response($response['fileContent'], 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$response['fileName'].'"',
        ])->header('Access-Control-Expose-Headers', 'Content-Disposition'); // Expose Content-Disposition header since it is not exposed by default to get the filename
    }
}
