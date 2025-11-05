<?php

namespace App\Http\Controllers\DailyTimeRecords;

use App\Enums\ApiErrorCode;
use App\Enums\PaginationType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\DailyTimeRecords\DailyTimeRecordRequest;
use App\Models\ComprehensiveRecords\Employee;
use App\Services\DailyTimeRecords\DailyTimeRecordManager;
use Exception;
use Illuminate\Http\JsonResponse;
use PaginationHelper;
use Symfony\Component\HttpFoundation\Response;

class DailyTimeRecordController extends ApiController
{
    private DailyTimeRecordManager $dailyTimeRecordService;

    public function __construct(DailyTimeRecordManager $dailyTimeRecordService)
    {
        $this->dailyTimeRecordService = $dailyTimeRecordService;
    }

    /**
     * Display all time logs. Can be filtered.
     */
    public function index(): JsonResponse
    {
        $dtr = $this->dailyTimeRecordService->all();
        $formatted = PaginationHelper::formatPagination($dtr);

        return $this->success($formatted, Response::HTTP_OK);

    }

    /**
     * Count warm bodies
     */
    public function countWarmBodies(): JsonResponse
    {
        $officeStatus = $this->dailyTimeRecordService->countWarmBodies();

        return $this->success(['data' => $officeStatus], Response::HTTP_OK);

    }

    /**
     * Display all DTRs per month.
     */
    public function viewDtrPerPeriodRange(Employee $employee, DailyTimeRecordRequest $request): JsonResponse
    {
        $validatedRequest = $request->validated();
        $this->authorize('viewDtrPerMonth', [$employee]);
        $dtrs = $this->dailyTimeRecordService->viewDtrPerPeriodRange($employee, $validatedRequest);
        $formatted = PaginationHelper::formatPagination($dtrs);

        return $this->success($formatted, Response::HTTP_OK);
    }

    /**
     * Display all warm bodies for the current date.
     */
    public function viewWarmBodiesToday(): JsonResponse
    {
        $dtrs = $this->dailyTimeRecordService->viewWarmBodiesToday();
        $formatted = PaginationHelper::formatPagination($dtrs);

        return $this->success($formatted, Response::HTTP_OK);
    }

    /**
     * Update existing DTRs and their time logs, as well as create new DTR for available dates.
     */
    public function update(Employee $employee, DailyTimeRecordRequest $request): JsonResponse
    {
        try {
            // Decode raw JSON payload into an associative array
            $payload = json_decode($request->getContent(), true);

            $validatedRequest = $request->validated();

            $dtrPayload = $payload['dtr'] ?? $validatedRequest['dtr'] ?? [];

            $dtrIds = data_get($dtrPayload, '*.id');

            $keysToMove = ['month', 'start_date', 'end_date'];
            foreach ($keysToMove as $key) {
                if (array_key_exists($key, $payload)) {
                    $dates[$key] = $payload[$key];
                }
            }

            $this->authorize('updateBulkDtr', [$employee, $dtrIds, $dates]);

            // Pass the array to your service
            $dtr = $this->dailyTimeRecordService->update($employee, $payload);
        } catch (Exception $e) {
            return $this->error(
                $e->getMessage(),
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::BAD_REQUEST
            );
        }

        return $this->success(['data' => $dtr], Response::HTTP_OK);
    }

    /**
     *  Search for time logs based on given name. This also accepts other filters like date, etc.
     */
    public function search(DailyTimeRecordRequest $dtrRequest): JsonResponse
    {
        $q = $dtrRequest->validated()['query'];
        $limit = $dtrRequest->validated('limit', 9);
        $isMyProfile = $dtrRequest->validated('is_my_profile', true);

        $timeLogs = $this->dailyTimeRecordService->searchTimeLogs($q, PaginationType::LENGTH_AWARE, $limit, $isMyProfile);
        $formatted = PaginationHelper::formatPagination($timeLogs);

        return $this->success($formatted, Response::HTTP_OK);

    }

    public function generateDailyTimeRecord(Employee $employee, DailyTimeRecordRequest $request): Response
    {
        // Merge query params so validated() works
        $request->merge($request->query());
        $validated = $request->validated();

        $startDate = $validated['start_date'] ?? '1900-01-01';
        $endDate = $validated['end_date'] ?? '2100-12-31';
        $sort = $validated['sort'] ?? 'asc';

        $response = $this->dailyTimeRecordService->generate($employee, $startDate, $endDate, $sort);

        return response($response['fileContent'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$response['fileName'].'"',
        ])->header('Access-Control-Expose-Headers', 'Content-Disposition');
    }

    /**
     * Get current user's last time log.
     */
    public function getLastTimeLog(Employee $employee): JsonResponse
    {
        $timeLogs = $this->dailyTimeRecordService->getLastTimeLog($employee);

        return $this->success(['data' => $timeLogs], Response::HTTP_OK);

    }
}
