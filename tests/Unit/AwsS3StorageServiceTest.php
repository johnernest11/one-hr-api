<?php

namespace Tests\Unit;

use App\Services\CloudStorageServices\AwsS3StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AwsS3StorageServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    public function test_it_can_upload_file_without_name(): void
    {
        $ownerId = 1;
        $path = "images/$ownerId/profile-pictures";
        $service = new AwsS3StorageService();
        $file = UploadedFile::fake()->image('fake_image.jpg');

        // This returns "path-to-file/filename.extension"
        $fullPath = $service->upload($path, $file);

        // Check if a filename is generated
        $this->assertTrue((bool) basename($fullPath));

        // Check if the path is correct
        $this->assertEquals(dirname($fullPath), $path);
    }

    public function test_it_can_upload_file_with_name(): void
    {
        $ownerId = 1;
        $path = "images/$ownerId/profile-pictures";
        $fileName = 'fake_image.jpg';

        $service = new AwsS3StorageService();
        $file = UploadedFile::fake()->image($fileName);

        // This returns "path-to-file/filename.extension"
        $fullPath = $service->upload($path, $file, $fileName);

        // Check if the name we provided is the same
        $this->assertEquals($fileName, basename($fullPath));

        // Check if the path is correct
        $this->assertEquals($path, dirname($fullPath));
    }

    public function test_it_can_upload_base64_image(): void
    {
        $ownerId = 1;
        $path = "images/$ownerId/profile-pictures";
        $fileName = 'fake_image.jpg';

        $service = new AwsS3StorageService();
        $file = UploadedFile::fake()->image($fileName);
        $mimeType = $file->getMimeType();
        $content = $file->getRealPath();
        $base64 = base64_encode(file_get_contents($content));

        $dataUri = "data:$mimeType;base64,".$base64;

        // This returns "path-to-file/filename.extension"
        $fullPath = $service->upload($path, $dataUri, $fileName);

        // Check if the name we provided is the same
        $this->assertEquals($fileName, basename($fullPath));

        // Check if the path is correct
        $this->assertEquals($path, dirname($fullPath));
    }

    public function test_it_can_delete_file(): void
    {
        $ownerId = 1;
        $path = "images/$ownerId/profile-pictures";
        $service = new AwsS3StorageService();
        $fileName = 'fake_image.jpg';
        $file = UploadedFile::fake()->image($fileName);
        $fullPath = $service->upload($path, $file, $fileName);
        $this->assertCount(1, Storage::disk('s3')->files($path));

        $service->delete($fullPath);
        $this->assertCount(0, Storage::disk('s3')->files($path));
    }
}
