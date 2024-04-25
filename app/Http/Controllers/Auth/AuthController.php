<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ApiErrorCode;
use App\Events\UserRegistered;
use App\Http\Controllers\ApiController;
use App\Http\Requests\AuthRequest;
use App\Models\User;
use App\Services\AppSettingsManager;
use App\Services\MfaPipelineManager;
use App\Services\User\UserAccountManager;
use App\Services\User\UserCredentialManager;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class AuthController extends ApiController
{
    private UserCredentialManager $userCredentialManager;

    private UserAccountManager $userAccountManager;

    private AppSettingsManager $appSettingsManager;

    private MfaPipelineManager $mfaPipelineManager;

    public function __construct(
        UserAccountManager $accManager,
        UserCredentialManager $credManager,
        AppSettingsManager $settingsManager,
        MfaPipelineManager $mfaPipelineManager,
    ) {
        $this->userAccountManager = $accManager;
        $this->userCredentialManager = $credManager;
        $this->appSettingsManager = $settingsManager;
        $this->mfaPipelineManager = $mfaPipelineManager;
    }

    /**
     * Grant the user an access token
     */
    public function store(AuthRequest $request): JsonResponse
    {
        $email = $request->get('email');
        $password = $request->get('password');
        $mobileNumber = $request->get('mobile_number');
        $user = null;

        // Users should be able to log in via email or mobile_number
        if ($email) {
            $user = $this->userCredentialManager->getUserViaEmailAndPassword($email, $password);
        } elseif ($mobileNumber) {
            $user = $this->userCredentialManager->getUserViaMobileNumberAndPassword($mobileNumber, $password);
        }

        if (! $user) {
            return $this->error(
                'The credentials provided were incorrect',
                Response::HTTP_UNAUTHORIZED,
                ApiErrorCode::INVALID_CREDENTIALS
            );
        }

        if (! $user->active) {
            return $this->error(
                'Account is deactivated.',
                Response::HTTP_FORBIDDEN,
                ApiErrorCode::FORBIDDEN
            );
        }

        // We proceed with the MFA flow if enabled
        $mfaConfig = $this->appSettingsManager->getMfaConfig();
        if ($mfaConfig['enabled']) {
            $mfaSteps = $mfaConfig['steps'];
            $mfaAttemptToken = $this->mfaPipelineManager->generateMfaAttemptToken($user, $mfaSteps);
            $data = ['mfa_token' => $mfaAttemptToken];

            return $this->success(['data' => $data], Response::HTTP_OK);
        }

        // For the token name, clients can optionally send 'My iPhone14', 'Google Chrome', etc.
        $clientName = $request->get('client_name') ?? 'api_token';
        $expiresAt = $this->getTokenExpiration();
        $token = $this->generateAuthToken($user, $expiresAt, $clientName);

        $withUserDetails = $request->get('with_user', false);
        $dataResponse = $this->composeUserTokenData($token, $clientName, $expiresAt, $user, $withUserDetails);

        return $this->success(['data' => $dataResponse], Response::HTTP_OK);
    }

    /**
     * Register a new user
     */
    public function register(AuthRequest $request): JsonResponse
    {
        $user = $this->userAccountManager->create($request->validated());

        // For the token name, clients can optionally send 'My iPhone14', 'Google Chrome', etc.
        $clientName = $request->get('client_name') ?? 'api_token';
        $expiresAt = $this->getTokenExpiration();
        $token = $this->generateAuthToken($user, $expiresAt, $clientName);
        $dataResponse = $this->composeUserTokenData($token, $clientName, $expiresAt, $user);

        UserRegistered::dispatch($user);

        return $this->success(['data' => $dataResponse], Response::HTTP_CREATED);
    }

    private function composeUserTokenData(string $token, string $clientName, Carbon $expiresAt, User $user, bool $withUserDetails = true): array
    {
        $data = [
            'token' => $token,
            'token_name' => $clientName,
            'expires_at' => $expiresAt,
        ];

        if ($withUserDetails) {
            $data['user'] = $user->fresh('userProfile');
        }

        return $data;
    }

    /** Create an authentication token for the user */
    abstract protected function generateAuthToken(User $user, Carbon $expiresAt, string $clientName): string;

    /** Get the expiration time for the token */
    abstract protected function getTokenExpiration(): Carbon;
}
