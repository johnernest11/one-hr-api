<?php

namespace App\Services\CloudStorageServices;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface CloudStorageManager
{
    /**
     * Upload a file
     */
    public function upload(string $path, UploadedFile|string $file, ?string $fileName = null): string;

    /**
     * Delete a file
     */
    public function delete(string $path): bool;

    /**
     * Generate a URL available by X seconds
     *
     * @param  int  $timeLimit  - time before the URL expires (in seconds)
     */
    public function generateTmpUrl($path, int $timeLimit): string;
}
