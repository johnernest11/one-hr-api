<?php

namespace App\Http\Controllers\DailyTimeRecords;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\ApiController;
use App\Http\Requests\DailyTimeRecords\QrCodeRequest;
use App\Models\ComprehensiveRecords\Employee;
use App\Services\DailyTimeRecords\QrCodeManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class QrCodeController extends ApiController
{
    private QrCodeManager $qrCodeService;

    public function __construct(QrCodeManager $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Store a newly created generated QR Code.
     */
    public function store(Employee $employee): JsonResponse
    {

        $qrCode = $this->qrCodeService->create($employee);

        return $this->success(['data' => $qrCode], Response::HTTP_CREATED);
    }

    /**
     * Show the QR code of the passed employee.
     */
    public function read(Employee $employee): JsonResponse
    {
        $qrCode = $this->qrCodeService->read($employee);

        return $this->success(['data' => $qrCode], Response::HTTP_OK);
    }

    /**
     * Update the status of a QR code based on the given employee ID.
     */
    public function update(Employee $employee, QrCodeRequest $request): JsonResponse
    {
        $qrCode = $this->qrCodeService->update($employee, $request->validated());

        return $this->success(['data' => $qrCode], Response::HTTP_OK);
    }

    /**
     * Verify a scanned QR and check if it belongs to which employee.
     */
    public function verifyQr(QrCodeRequest $request): JsonResponse
    {
        try {
            $employee = $this->qrCodeService->verifyQr($request->validated());
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

        return $this->success(['data' => $employee], Response::HTTP_OK);
    }
}
