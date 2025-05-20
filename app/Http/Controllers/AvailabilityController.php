<?php

namespace App\Http\Controllers;

use App\Http\Requests\AvailabilityRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AvailabilityController extends ApiController
{
    /**
     * Get email availability
     */
    public function getEmailAvailability(AvailabilityRequest $request): JsonResponse
    {
        $email = strtolower($request->get('value'));
        $excludedId = $request->get('excluded_id');
        $query = User::whereEmail($email);

        if ($excludedId) {
            $query->whereNot('id', $excludedId);
        }

        $isAvailable = ! $query->first();
        $data = ['is_available' => $isAvailable];

        return $this->success(['data' => $data], Response::HTTP_OK);
    }

    public function getMobileNumberAvailability(AvailabilityRequest $request): JsonResponse
    {
        $mobileNumber = $request->get('value');
        $excludedId = $request->get('excluded_id');

        $userProfile = DB::connection('mysql')
            ->table('user_profiles')
            ->where('mobile_number', $mobileNumber)
            ->first();

        $isAvailable = true;

        if ($userProfile) {
            $user = DB::connection('one_account')
                ->table('users')
                ->where('id', $userProfile->user_id)
                ->first();

            if ($user && (! $excludedId || $user->id != $excludedId)) {
                $isAvailable = false;
            }
        }
        $data = ['is_available' => $isAvailable];

        return $this->success(['data' => $data], Response::HTTP_OK);
    }
}
