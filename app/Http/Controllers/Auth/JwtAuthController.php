<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Services\AppSettingsManager;
use App\Services\Authentication\Interfaces\AuthTokenManager;
use App\Services\MfaOrchestrator;
use App\Services\User\UserAccountManager;
use App\Services\User\UserCredentialManager;
use Carbon\Carbon;

class JwtAuthController extends AuthController
{
    private AuthTokenManager $authTokenManager;

    public function __construct(
        UserAccountManager $accManager,
        UserCredentialManager $credManager,
        AuthTokenManager $authTokenManager,
        AppSettingsManager $settingsManager,
        MfaOrchestrator $mfaPipelineManager,
    ) {
        parent::__construct($accManager, $credManager, $settingsManager, $mfaPipelineManager);
        $this->authTokenManager = $authTokenManager;
    }

    /** {@inheritDoc} */
    public function generateAuthToken(User $user, Carbon $expiresAt, string $clientName): string
    {
        return $this->authTokenManager->generateToken($user, $expiresAt, $clientName);
    }

    /** {@inheritDoc} */
    public function getTokenExpiration(): Carbon
    {
        return now()->addMinutes(config('jwt.lifetime_minutes'));
    }
}
