<?php

namespace App\Http\Controllers;

use App\Enums\ApiErrorCode;
use App\Http\Requests\ProfileRequest;
use App\Models\User;
use App\Services\CloudStorageServices\CloudStorageManager;
use App\Services\User\UserManager;
use App\Traits\Controllers\CanMoveUploadProfilePhotoToCloud;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends ApiController
{
    use CanMoveUploadProfilePhotoToCloud;

    private UserManager $userService;

    public function __construct(UserManager $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Fetch the user information of the currently authenticated user
     */
    public function view(): JsonResponse
    {
        $user = $this->userService->read(auth()->user()->id);

        return $this->success(['data' => $user], Response::HTTP_OK);
    }

    /**
     * Update the authenticated user's profile
     */
    public function update(ProfileRequest $request): JsonResponse
    {
        $user = $this->userService->update(auth()->user()->id, $request->validated());

        return $this->success(['data' => $user], Response::HTTP_OK);
    }

    /**
     * Upload profile picture
     */
    public function uploadProfilePicture(ProfileRequest $request, CloudStorageManager $cloudStorage): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $file = $request->file('photo');
        $result = $this->moveProfilePictureToCloud($user, $file, $cloudStorage, $this->userService);

        return $this->success(['data' => $result], Response::HTTP_OK);
    }

    /**
     * Change user password
     */
    public function changePassword(ProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $oldPassword = $request->get('old_password');
        $newPassword = $request->get('password');
        $updatedUser = $this->userService->updatePassword($user, $newPassword, $oldPassword);

        if (! $updatedUser) {
            return $this->error(
                'Old password is incorrect',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ApiErrorCode::INCORRECT_OLD_PASSWORD
            );
        }

        return $this->success(['message' => 'Password changed successfully'], Response::HTTP_OK);
    }
}
