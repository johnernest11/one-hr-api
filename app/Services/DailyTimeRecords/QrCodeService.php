<?php

namespace App\Services\DailyTimeRecords;

use App\Models\ComprehensiveRecords\Employee;
use App\Models\DailyTimeRecords\QrCode;
use App\Traits\Services\CanBuildPagination;
use Carbon\Carbon;
use Crypt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class QrCodeService implements QrCodeManager
{
    use CanBuildPagination;

    public const MAX_TRANSACTION_DEADLOCK_ATTEMPTS = 5;

    private QrCode $model;

    public function __construct(QrCode $model)
    {
        $this->model = $model;

    }

    /**
     * {@inheritDoc}
     */
    public function create(Employee $employee): QrCode
    {
        return DB::transaction(function () use ($employee) {
            $encryptedId = Crypt::encrypt($employee->id_number);
            $issuedAt = Carbon::now();

            // Check if a QR already exists for the employee
            // If yes, update the qr_code_value and last_generated_at
            // If no, create record
            $hasQr = $this->model->whereBelongsTo($employee)->exists();
            if ($hasQr) {
                $qrCode = $this->model->whereBelongsTo($employee)->firstOrFail();
                $qrCode->update([
                    'qr_code_value' => $encryptedId,
                    'last_generated_at' => $issuedAt,
                ]);

                return $qrCode->fresh();
            }

            // Encrypt passed employee ID number
            // Take current datetime as last_generated_at
            // Set is_active as true

            $data = [
                'employee_id' => $employee->id,
                'qr_code_value' => $encryptedId,
                'last_generated_at' => $issuedAt,
                'is_active' => true,
            ];

            $qrCode = $this->model->create($data);

            return $qrCode;
        }, self::MAX_TRANSACTION_DEADLOCK_ATTEMPTS);
    }

    /** {@inheritDoc} */
    public function read(Employee $employee): ?QrCode
    {
        $qrCode = $this->model->where('employee_id', $employee->id)->first();

        return $qrCode;
    }

    /**
     * {@inheritDoc}
     */
    public function update(Employee $employee, array $newQrInfo): QrCode
    {
        return DB::transaction(function () use ($employee, $newQrInfo) {
            $qrCode = $this->model->whereBelongsTo($employee)->firstOrFail();
            $qrCode->update($newQrInfo);

            return $qrCode->fresh();
        }, self::MAX_TRANSACTION_DEADLOCK_ATTEMPTS);
    }

    /** {@inheritDoc} */
    public function verifyQr(array $request): Employee
    {
        // Decrypt QR
        // Check if the decrypted id number exists
        // If it does, return the employee record
        $decryptedData = Crypt::decrypt($request['scanned_qr']);
        $employee = Employee::where('id_number', $decryptedData)->firstOrFail();

        // Ensure that the QR Code of the employee is active.
        $isActive = $this->model->whereBelongsTo($employee)->firstOrFail()->is_active;
        if (! $isActive) {
            throw new AuthorizationException('QR code is not active.');
        }

        return $employee;
    }
}
