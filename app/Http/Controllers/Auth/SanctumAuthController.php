<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\AuthRequest;
use App\Models\PersonalAccessToken;
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

    /**
     * Refresh Current Access and Refresh Token
     */
    public function refreshCurrentTokens(AuthRequest $request): JsonResponse
    {
        $currentRefreshToken = $request->bearerToken();
        $refreshToken = PersonalAccessToken::findToken($currentRefreshToken);

        if (! $refreshToken || ! $refreshToken->can('refresh') || $refreshToken->expires_at->isPast()) {
            return $this->error('Invalid or expired refresh token', Response::HTTP_UNAUTHORIZED);
        }

        $user = $refreshToken->tokenable;
        $this->tokenManager->invalidateToken($refreshToken);

        $accessTokenExpiresAt = $this->getTokenExpiration();
        $refreshTokenExpiresAt = $this->getTokenExpiration()->addSeconds(10); // @todo Update to 5 days once testing is done.

        $newAccessToken = $user->createToken('api_token', ['*'], $accessTokenExpiresAt)->plainTextToken;
        $newRefreshToken = $user->createToken('refresh_token', ['refresh'], $refreshTokenExpiresAt)->plainTextToken;

        $data = [
            'token' => $newAccessToken,
            'token_name' => 'api_token',
            'expires_at' => $accessTokenExpiresAt,
            'refresh_token' => $newRefreshToken,
            'refresh_token_name' => 'refresh_token',
            'refresh_token_expires_at' => $refreshTokenExpiresAt,
            'user' => $user,
        ];

        return $this->success(['data' => $data], Response::HTTP_CREATED);
    }
}
