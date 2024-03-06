<?php

namespace App\Http\Controllers;

use App\Enums\ApiErrorCode;
use App\Events\UserRegistered;
use App\Http\Requests\AuthRequest;
use App\Interfaces\Authentication\TokenAuthServiceInterface;
use App\Interfaces\HttpResources\UserServiceInterface;
use App\Models\User;
use App\Services\Authentication\TokenAuthService;
use Illuminate\Http\JsonResponse;
use Propaganistas\LaravelPhone\PhoneNumber;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends ApiController
{
    private TokenAuthService $authService;

    public function __construct(TokenAuthServiceInterface $authService)
    {
        $this->authService = $authService;
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
            $user = $this->authService->getUserViaEmailAndPassword($email, $password);
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
            $user = $this->authService->getUserViaMobileNumberAndPassword($mobileNumber, $password);
        }

        if (! $user) {
            return $this->error(
                'The credentials provided were incorrect',
                Response::HTTP_UNAUTHORIZED,
                ApiErrorCode::INVALID_CREDENTIALS
            );
        }

        // For the token name, clients can optionally send 'My iPhone14', 'Google Chrome', etc.
        $tokenName = $request->get('client_name') ?? 'api_token';

        $withUserDetails = $request->get('with_user', false);
        $data = $this->authService->bindAuthToken($user, $tokenName, 12, $withUserDetails);

        return $this->success(['data' => $data], Response::HTTP_OK);
    }

    /**
     * Register a new user
     */
    public function register(AuthRequest $request, UserServiceInterface $userService): JsonResponse
    {
        $user = $userService->create($request->validated());

        // For the token name, clients can optionally send 'My iPhone14', 'Google Chrome', etc.
        $tokenName = $request->get('client_name') ?? 'api_token';

        $data = $this->authService->bindAuthToken($user, $tokenName);
        UserRegistered::dispatch($user);

        return $this->success(['data' => $data], Response::HTTP_CREATED);
    }

    /**
     * Revoke the current access token of the user
     */
    public function destroy(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $this->authService->destroyCurrentAuthToken($user);

        return $this->success(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Retrieve all the access tokens of a user
     */
    public function fetch(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $tokens = $this->authService->getUserAuthTokens($user);

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
        $this->authService->destroyAccessTokens($user, $tokensToRevoke);

        return $this->success(null, Response::HTTP_NO_CONTENT);
    }
}
