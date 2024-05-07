<?php

namespace App\Services\CloudStorageServices;

use Storage;
use Str;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AwsS3StorageService implements CloudStorageManager
{
    /** {@inheritDoc} */
    public function upload(string $path, string|UploadedFile $file, ?string $fileName = null): string
    {
        // Remove "/" from the right and left side of the string if any
        $path = trim($path, '/');

        if (! $fileName && $file instanceof UploadedFile) {
            /**
             * We generate a file name for uploaded files directly from user requests.
             * Laravel casts uploaded files to the UploadedFile class
             */
            $fileName = Str::uuid().'.'.$file->getClientOriginalExtension();
        } elseif (! $fileName) {
            /**
             * No filename provided and no uploaded file detected.
             * Since we don't check for base64 data in this context,
             * we simply use a UUID for the filename.
             */
            $fileName = Str::uuid();
        }

        $success = Storage::disk('s3')->putFileAs($path, $file, $fileName);

        if (! $success) {
            throw new UploadException('Unable to upload file to S3');
        }

        return $path.'/'.$fileName;
    }

    /** {@inheritDoc} */
    public function delete(string $path): bool
    {
        return Storage::disk('s3')->delete($path);
    }

    /**
     * Generate a presigned URL
     *
     * @see https://docs.aws.amazon.com/AmazonS3/latest/userguide/ShareObjectPreSignedURL.html
     *
     * @param  int  $timeLimit  (in seconds)
     */
    public function generateTmpUrl($path, int $timeLimit): string
    {
        return Storage::disk('s3')->temporaryUrl($path, now()->addSeconds($timeLimit));
    }
}
