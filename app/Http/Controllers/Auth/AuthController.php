<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ApiErrorCode;
use App\Enums\AuthenticationType;
use App\Enums\VerificationMethod;
use App\Events\UserRegistered;
use App\Http\Controllers\ApiController;
use App\Http\Requests\AuthRequest;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\AppSettingsManager;
use App\Services\MfaOrchestrator;
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

    private MfaOrchestrator $mfaOrchestrator;

    public function __construct(
        UserAccountManager $accManager,
        UserCredentialManager $credManager,
        AppSettingsManager $settingsManager,
        MfaOrchestrator $mfaOrchestrator,
    ) {
        $this->userAccountManager = $accManager;
        $this->userCredentialManager = $credManager;
        $this->appSettingsManager = $settingsManager;
        $this->mfaOrchestrator = $mfaOrchestrator;
    }

    /**
     * Grant the user an access token or handle SSO login.
     */
    public function store(AuthRequest $request): JsonResponse
    {
        if ($request->has('token')) {
            return $this->handleSSOLogin($request);
        }

        return $this->handleWebkitLogin($request);
    }

    /**
     * Grant the user an access token
     */
    public function handleWebkitLogin(AuthRequest $request): JsonResponse
    {
        $username = $request->get('username');
        $email = $request->get('email');
        $password = $request->get('password');
        $mobileNumber = $request->get('mobile_number');
        $user = null;

        // Users should be able to log in via email or mobile_number
        if ($email) {
            $user = $this->userCredentialManager->getUserViaEmailAndPassword($email, $password);
        } elseif ($mobileNumber) {
            $user = $this->userCredentialManager->getUserViaMobileNumberAndPassword($mobileNumber, $password);
        } elseif ($username) {
            $user = $this->userCredentialManager->getUserViaUsernameAndPassword($username, $password);
        }

        // Check if the user has the 'admin' role BEFORE proceeding with token generation
        if (! $user->roles()->where('name', 'admin')->exists()) {
            return $this->error(
                'You do not have administrator privileges.',
                Response::HTTP_FORBIDDEN,
                ApiErrorCode::FORBIDDEN
            );
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

        // For the token name, clients can optionally send 'My iPhone14', 'Google Chrome', etc.
        $clientName = $request->get('client_name', 'api_token');
        $authType = $request->get('auth_type', AuthenticationType::SANCTUM->value);
        $withUserDetails = $request->get('with_user', false);

        // We proceed with the MFA flow if enabled
        $mfaConfig = $this->appSettingsManager->getMfaConfig();
        if ($mfaConfig['enabled']) {
            $mfaSteps = $mfaConfig['steps'];
            $authMeta = ['token_name' => $clientName, 'auth_type' => $authType, 'with_user' => $withUserDetails];
            $mfaAttempt = $this->mfaOrchestrator->generateMfaAttemptToken($user, $mfaSteps, $authMeta);
            $this->mfaOrchestrator->runSecretGeneration($mfaAttempt['token']);

            $data = [
                'mfa_token' => $mfaAttempt['token'],
                'mfa_token_expires_at' => $mfaAttempt['expires_at'],
                'mfa_steps' => $mfaAttempt['steps'],
            ];

            // Deliver the MFA code if the first MFA step supports code delivery
            $firstStep = VerificationMethod::from($mfaSteps[0]);
            if ($this->mfaOrchestrator->stepSupportsCodeDelivery($firstStep)) {
                $mfaAttemptRecord = $this->mfaOrchestrator->getMfaAttemptFromToken($mfaAttempt['token']);
                $this->mfaOrchestrator->runCodeDelivery($mfaAttemptRecord);
            }

            return $this->success(['data' => $data], Response::HTTP_OK);
        }

        // Continue with the login if MFA is not enabled
        $expiresAt = $this->getTokenExpiration();
        $token = $this->generateAuthToken($user, $expiresAt, $clientName);
        $dataResponse = $this->composeUserTokenData($token, $clientName, $expiresAt, $user, $withUserDetails);

        return $this->success(['data' => $dataResponse], Response::HTTP_OK);
    }

    /**
     * Handles the SSO login using a JWT token.
     * and generate token using generateAuthToken()
     */
    protected function handleSSOLogin(AuthRequest $request): JsonResponse
    {
        $ssoToken = $request->get('token');

        // Find the personal access token associated with the SSO token
        $personalAccessToken = PersonalAccessToken::findToken($ssoToken);

        // Validate whether the token exists
        if (! $personalAccessToken) {
            return $this->error(
                'Invalid SSO token (Personal Access Token not found)',
                Response::HTTP_UNAUTHORIZED,
                ApiErrorCode::INVALID_CREDENTIALS
            );
        }

        // Find the user associated with the personal access token
        $user = User::on($personalAccessToken->getConnectionName())->findOrFail($personalAccessToken->tokenable_id);

        // Check if the user profile allows access
        if (! UserProfile::where('user_id', $user->id)->exists()) {
            return $this->error(
                'Your account is not allowed to access the system.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ApiErrorCode::FORBIDDEN
            );
        }

        // If the token is valid and user is active, use this existing token
        $expiresAt = now()->addMinutes($this->getTokenExpiration()->diffInMinutes(now())); // Adjust this logic as necessary
        $deviceName = $request->get('device_name', 'HR-CARES');

        // Compose the response data using the existing token
        $dataResponse = $this->composeUserTokenData($ssoToken, $deviceName, $expiresAt, $user, true);

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
