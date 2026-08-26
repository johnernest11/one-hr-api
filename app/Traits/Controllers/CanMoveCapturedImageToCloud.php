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
        try {
            $folder = 'images/timelogs/'.now()->format('Y-m-d');
            if ($employeeId) {
                $folder .= '/'.$employeeId;
            }

            $extension = $file->getClientOriginalExtension()
                ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION)
                ?: 'jpg';

            $fileName = 'log_'.now()->timestamp.'_'.Str::random(8).'.'.$extension;

            $fullPath = $cloudStorage->upload($folder, $file, $fileName);

            return $fullPath;
        } catch (\Exception $e) {
            Log::error('Failed to upload captured image: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return null;
        }
    }
}
