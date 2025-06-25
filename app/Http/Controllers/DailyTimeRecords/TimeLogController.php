<?php

namespace App\Http\Controllers\DailyTimeRecords;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\ApiController;
use App\Http\Requests\DailyTimeRecords\TimeLogRequest;
use App\Services\DailyTimeRecords\QrCodeManager;
use App\Services\DailyTimeRecords\TimeLogManager;
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
     * Verify a scanned QR and check if it belongs to which employee.
     * Afterwards, log time for that employee.
     */
    public function logTime(TimeLogRequest $request): JsonResponse
    {
        try {
            $employee = $this->qrCodeService->verifyQr($request->validated());
            $logTime = $this->timeLogService->create($employee);

        } catch (DecryptException $e) {
            // Catch decryption error wherein the payload is invalid, and no employee is found.
            return $this->error(
                'QR code is invalid.',
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::BAD_REQUEST
            );
        } catch (ModelNotFoundException $e) {
            // Catch error wherein the employee is not found.
            return $this->error(
                'There is no employee with that QR code.',
                Response::HTTP_NOT_FOUND,
                ApiErrorCode::RESOURCE_NOT_FOUND
            );
        } catch (AuthorizationException $e) {
            // Check if QR code is inactive.
            return $this->error(
                $e->getMessage(),
                Response::HTTP_UNAUTHORIZED,
                ApiErrorCode::UNAUTHORIZED
            );
        }

        return $this->success(['data' => $logTime], Response::HTTP_OK);
    }
}
