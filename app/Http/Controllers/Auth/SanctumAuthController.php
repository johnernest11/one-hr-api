<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\AuthRequest;
use App\Models\User;
use App\Services\AppSettingsManager;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use App\Services\MfaOrchestrator;
use App\Services\User\UserAccountManager;
use App\Services\User\UserCredentialManager;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SanctumAuthController extends AuthController
{
    private PersistentAuthTokenManager $tokenManager;

    public function __construct(
        UserAccountManager $accManager,
        UserCredentialManager $credManager,
        PersistentAuthTokenManager $tokenManager,
        AppSettingsManager $settingsManager,
        MfaOrchestrator $mfaPipelineManager
    ) {
        parent::__construct($accManager, $credManager, $settingsManager, $mfaPipelineManager);
        $this->tokenManager = $tokenManager;
    }

    /**
     * Retrieve all the access tokens of a user
     */
    public function fetch(): JsonResponse
    {
        /** @var User $user */
        $user = auth('token')->user();
        $tokens = $this->tokenManager->getAllActiveTokens($user);

        return $this->success(['data' => $tokens], Response::HTTP_OK);
    }

    /**
     * Revoke specified access tokens owned by the user
     */
    public function invalidateMultiple(AuthRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth('token')->user();
        $tokensToRevoke = $request->get('token_ids');
        $this->tokenManager->invalidateMultipleTokens($user, $tokensToRevoke);

        return $this->success(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Revoke the current access token of the user
     */
    public function invalidateCurrent(AuthRequest $request): JsonResponse
    {
        $token = $request->bearerToken();

        if ($token) {
            $this->tokenManager->invalidateToken($token);
        }

        return $this->success(null, Response::HTTP_NO_CONTENT);
    }

    /** {@inheritDoc} */
    public function generateAuthToken(User $user, Carbon $expiresAt, string $clientName): string
    {
        return $this->tokenManager->generateToken($user, $expiresAt, $clientName);
    }

    /** {@inheritDoc} */
    public function getTokenExpiration(): Carbon
    {
        return now()->addMinutes(config('sanctum.expiration'));
    }
}
