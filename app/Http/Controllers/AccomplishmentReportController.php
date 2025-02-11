<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccomplishmentReportRequest;
use App\Models\AccomplishmentReport;
use App\Models\User;
use App\Services\AccomplishmentReport\AccomplishmentReportManager;
use PaginationHelper;
use Symfony\Component\HttpFoundation\Response;

class AccomplishmentReportController extends ApiController
{
    private AccomplishmentReportManager $accomplishmentReportManager;

    public function __construct(AccomplishmentReportManager $arManager)
    {
        $this->accomplishmentReportManager = $arManager;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $reports = $this->accomplishmentReportManager->all();
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
        $report = $this->accomplishmentReportManager->create($user, $request->validated());
        // @todo create event
        //PersonnelMembershipCreated::dispatch($membership);

        return $this->success(['data' => $report], Response::HTTP_CREATED);

    }

    /**
     * Display the specified resource.
     */
    public function show(AccomplishmentReport $accomplishmentReport)
    {
        $report = $this->accomplishmentReportManager->read($accomplishmentReport);

        return $this->success(['data' => $report], Response::HTTP_CREATED);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AccomplishmentReportRequest $request, AccomplishmentReport $accomplishmentReport)
    {
        $updatedReport = $this->accomplishmentReportManager->update($accomplishmentReport, $request->validated());

        return $this->success(['data' => $updatedReport], Response::HTTP_OK);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function generateAccomplishmentReport(AccomplishmentReport $accomplishmentReport)
    {
        $response = $this->accomplishmentReportManager->generate($accomplishmentReport);

        return $response; //@todo update response
    }
}
