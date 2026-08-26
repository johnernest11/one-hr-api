<?php

namespace App\Traits\Controllers;

use App\Services\CloudStorageServices\CloudStorageManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait CanMoveCapturedImageToCloud
{
    /**
     * Upload a captured image to cloud storage and return its S3 path.
     *
     * @return string|null The S3 path of the uploaded file
     */
    protected function moveCapturedImageToCloud(
        UploadedFile $file,
        CloudStorageManager $cloudStorage,
        int|string|null $employeeId = null
    ): ?string {
        // try {
        $folder = 'images/timelogs/'.now()->format('Y-m-d');
        if ($employeeId) {
            $folder .= '/'.$employeeId;
        }

        Log::info('DEBUG [moveCapturedImageToCloud]: Starting upload...', [
            'file_original_name' => $file->getClientOriginalName(),
            'file_mime_type' => $file->getMimeType(),
            'file_size_bytes' => $file->getSize(),
            'file_is_valid' => $file->isValid(),
            'file_error_code' => $file->getError(),
            'employee_id' => $employeeId,
        ]);

        if (! $file->isValid()) {
            Log::warning('DEBUG [moveCapturedImageToCloud]: File is invalid or upload limit exceeded!', [
                'error_code' => $file->getError(),
            ]);

            return null;
        }

        $extension = $file->getClientOriginalExtension()
            ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION)
            ?: 'jpg';

        $fileName = 'log_'.now()->timestamp.'_'.Str::random(8).'.'.$extension;

        Log::info('DEBUG [moveCapturedImageToCloud]: Calling $cloudStorage->upload()', [
            'target_folder' => $folder,
            'target_file' => $fileName,
        ]);

        $fullPath = $cloudStorage->upload($folder, $file, $fileName);

        Log::info('DEBUG [moveCapturedImageToCloud]: Upload completed successfully', [
            'returned_full_path' => $fullPath,
        ]);

        return $fullPath;
        // } catch (\Exception $e) {
        // Log::error('Failed to upload captured image: '.$e->getMessage(), [
        // 'exception' => $e,
        // ]);

        // return null;
        // }
    }
}
