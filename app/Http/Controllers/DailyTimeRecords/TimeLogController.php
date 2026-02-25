<?php

namespace App\Http\Controllers\DailyTimeRecords;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\ApiController;
use App\Http\Requests\DailyTimeRecords\TimeLogRequest;
use App\Models\Libraries\Office;
use App\Services\DailyTimeRecords\QrCodeManager;
use App\Services\DailyTimeRecords\TimeLogManager;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TimeLogController extends ApiController
{
    private QrCodeManager $qrCodeService;

    private TimeLogManager $timeLogService;

    public function __construct(QrCodeManager $qrCodeService, TimeLogManager $timeLogService)
    {
        $this->qrCodeService = $qrCodeService;
        $this->timeLogService = $timeLogService;
    }

    /**
     * Verify a scanned QR and log time for the employee.
     */
    public function logTime(TimeLogRequest $request): JsonResponse
    {
        try {
            $employee = $this->qrCodeService->verifyQr($request->validated());

            $capturedImage = $request->file('captured_image');
            $office = Office::findOrFail($request->input('office_id'));

            $logTime = $this->timeLogService->create($employee, $capturedImage, $office);

        } catch (DecryptException $e) {
            return $this->error(
                'QR code is invalid.',
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::BAD_REQUEST
            );

        } catch (ModelNotFoundException $e) {
            return $this->error(
                'There is no employee with that QR code.',
                Response::HTTP_NOT_FOUND,
                ApiErrorCode::RESOURCE_NOT_FOUND
            );

        } catch (AuthorizationException $e) {
            return $this->error(
                $e->getMessage(),
                Response::HTTP_UNAUTHORIZED,
                ApiErrorCode::UNAUTHORIZED
            );

        } catch (Exception $e) {
            return $this->error(
                $e->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ApiErrorCode::VALIDATION
            );
        }

        return $this->success([
            'data' => $logTime,
            'captured_image_url' => $logTime->captured_image_url,
        ], Response::HTTP_OK);
    }
}
