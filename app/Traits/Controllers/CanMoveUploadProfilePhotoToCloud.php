<?php

namespace App\Traits\Controllers;

use App\Models\User;
use App\Services\CloudStorageServices\CloudStorageManager;
use App\Services\User\UserAccountManager;
use Illuminate\Http\UploadedFile;

trait CanMoveUploadProfilePhotoToCloud
{
    protected function moveProfilePictureToCloud(
        User $user,
        UploadedFile $file,
        CloudStorageManager $cloudStorage,
        UserAccountManager $userAccountManager
    ): array {
        $path = "images/$user->id/profile-pictures";

        // we get the old path, so we can mark for deletion after the upload
        $oldPath = $user->userProfile->profile_picture_path;

        $fullPath = $cloudStorage->upload($path, $file);
        $updatedUser = $userAccountManager->update($user, ['profile_picture_path' => $fullPath]);

        // We delete the old profile picture
        if ($oldPath) {
            $cloudStorage->delete($oldPath);
        }

        return [
            'owner_id' => $updatedUser->id,
            'path' => $fullPath,
            'url' => $updatedUser->userProfile->profile_picture_url,
        ];
    }
}
