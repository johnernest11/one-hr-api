<?php

namespace App\Services\CloudStorageServices;

use App\Enums\FileSystem;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * Stream files from the storage
     */
    public function stream(string $path, FileSystem $disk = FileSystem::CLOUD): StreamedResponse;

    /**
     * Check if file exists in the storage
     */
    public function isExisting(string $path, FileSystem $disk = FileSystem::CLOUD): bool;

    /**
     * Transfer local file to cloud
     */
    public function transfer(string $path): bool|UploadException;
}
