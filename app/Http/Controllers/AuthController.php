<?php

namespace App\Http\Controllers;

use App\Enums\ApiErrorCode;
use App\Events\UserRegistered;
use App\Http\Requests\AuthRequest;
use App\Interfaces\Authentication\PersistentAuthTokenManager;
use App\Interfaces\HttpResources\UserServiceInterface;
use App\Models\User;
use App\Traits\Controllers\CanComposeUserTokenData;
use Illuminate\Http\JsonResponse;
use Propaganistas\LaravelPhone\PhoneNumber;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends ApiController
{
    use CanComposeUserTokenData;

    private PersistentAuthTokenManager $sanctumAuthService;

    private UserServiceInterface $userService;

    public function __construct(UserServiceInterface $userService)
    {
        $this->sanctumAuthService = resolve(PersistentAuthTokenManager::class);
        $this->userService = $userService;
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
            $user = $this->userService->getUserViaEmailAndPassword($email, $password);
        } elseif ($mobileNumber) {
            /**
             * Since we save the mobile (and phone) numbers in international format,
             * we will mutate it if clients send in national format
             * ex: 09064647295 -> +639064647295
             *
             * @Note
             * We ignore the country format if we're running tests, since seeding can produce some malformed numbers
             */
            $mobileNumber = (new PhoneNumber($mobileNumber, 'PH'))->formatE164();
            $user = $this->userService->getUserViaMobileNumberAndPassword($mobileNumber, $password);
        }

        if (! $user) {
            return $this->error(
                'The credentials provided were incorrect',
                Response::HTTP_UNAUTHORIZED,
                ApiErrorCode::INVALID_CREDENTIALS
            );
        }

        // For the token name, clients can optionally send 'My iPhone14', 'Google Chrome', etc.
        $clientName = $request->get('client_name') ?? 'api_token';
        $expiresAt = now()->addMinutes(config('sanctum.expiration'));
        $token = $this->sanctumAuthService->generateToken($user, $expiresAt, $clientName);

        $withUserDetails = $request->get('with_user', false);
        $dataResponse = $this->composeUserTokenData($token, $clientName, $expiresAt, $user, $withUserDetails);

        return $this->success(['data' => $dataResponse], Response::HTTP_OK);
    }

    /**
     * Register a new user
     */
    public function register(AuthRequest $request, UserServiceInterface $userService): JsonResponse
    {
        $user = $userService->create($request->validated());

        // For the token name, clients can optionally send 'My iPhone14', 'Google Chrome', etc.
        $clientName = $request->get('client_name') ?? 'api_token';
        $expiresAt = now()->addMinutes(config('sanctum.expiration'));
        $token = $this->sanctumAuthService->generateToken($user, $expiresAt, $clientName);
        $dataResponse = $this->composeUserTokenData($token, $clientName, $expiresAt, $user);

        UserRegistered::dispatch($user);

        return $this->success(['data' => $dataResponse], Response::HTTP_CREATED);
    }

    /**
     * Revoke the current access token of the user
     */
    public function destroy(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $this->sanctumAuthService->invalidateCurrentToken($user);

        return $this->success(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Retrieve all the access tokens of a user
     */
    public function fetch(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $tokens = $this->sanctumAuthService->getAllActiveTokens($user);

        return $this->success(['data' => $tokens], Response::HTTP_OK);
    }

    /**
     * Revoke specified access tokens owned by the user
     */
    public function revoke(AuthRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $tokensToRevoke = $request->get('token_ids');
        $this->sanctumAuthService->invalidateMultipleTokens($user, $tokensToRevoke);

        return $this->success(null, Response::HTTP_NO_CONTENT);
    }
}
