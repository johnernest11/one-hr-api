<?php

namespace App\Auth;

use App\Services\Authentication\Interfaces\AuthTokenManager;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use App\Services\User\UserAccountManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MultiTokenGuard implements Guard
{
    private ?Authenticatable $user;

    private Request $request;

    public function __construct(Request $request)
    {
        $this->user = null;
        $this->request = $request;
    }

    /**
     * {@inheritDoc}
     */
    public function check(): bool
    {
        $token = $this->request->bearerToken();

        if (config('auth.mechanism.sanctum_enabled')) {
            if ($token) {
                $sanctumAuthService = resolve(PersistentAuthTokenManager::class);
                $sanctumVerified = $sanctumAuthService->tokenIsValid($token);
                if ($sanctumVerified) {
                    return true;
                }
            }
        }

        if (config('auth.mechanism.jwt_enabled')) {
            if ($token) {
                $jwtAuthService = resolve(AuthTokenManager::class);
                $jwtVerified = $jwtAuthService->tokenIsValid($token);
                if ($jwtVerified) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function guest(): bool
    {
        return ! $this->check();
    }

    /**
     * {@inheritDoc}
     */
    public function user(): bool|Authenticatable|null
    {
        if (! is_null($this->user)) {
            return $this->user;
        }

        $token = $this->request->bearerToken();

        if (config('auth.mechanism.sanctum_enabled')) {
            if ($token) {
                $sanctumAuthService = resolve(PersistentAuthTokenManager::class);
                $foundUser = $sanctumAuthService->getTokenOwner($token);

                if (! is_null($foundUser)) {
                    $this->user = $foundUser;

                    return $this->user;
                }
            }
        }

        if (config('auth.mechanism.jwt_enabled')) {
            if ($token) {
                $jwtAuthService = resolve(AuthTokenManager::class);
                $foundUser = $jwtAuthService->getTokenOwner($token);

                if (! is_null($foundUser)) {
                    $this->user = $foundUser;

                    return $this->user;
                }
            }
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function id(): mixed
    {
        if (! is_null($this->user)) {
            return $this->user->id;
        }

        $token = $this->request->bearerToken();

        if (config('auth.mechanism.sanctum_enabled')) {
            if ($token) {
                $sanctumAuthService = resolve(PersistentAuthTokenManager::class);

                $foundUser = $sanctumAuthService->getTokenOwner($token);
                if (! is_null($foundUser)) {
                    return $foundUser->id;
                }
            }
        }

        if (config('auth.mechanism.jwt_enabled')) {
            if ($token) {
                $jwtAuthService = resolve(AuthTokenManager::class);

                $foundUser = $jwtAuthService->getTokenOwner($token);
                if (! is_null($foundUser)) {
                    return $foundUser->id;
                }
            }
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function validate(array $credentials = []): bool
    {
        if (! isset($credentials['password'])) {
            throw new InvalidArgumentException('The credentials array should have a `password` key');
        }

        $userService = resolve(UserAccountManager::class);

        if (isset($credentials['email'])) {
            return (bool) $userService->getUserViaEmailAndPassword($credentials['email'], $credentials['password']);
        }

        if (isset($credentials['mobile_number'])) {
            return (bool) $userService->getUserViaMobileNumberAndPassword($credentials['mobile_number'], $credentials['password']);
        }

        throw new InvalidArgumentException('The credentials array should either have an `email` or `mobile_number` key');
    }

    /**
     * {@inheritDoc}
     */
    public function hasUser(): bool
    {
        if ($this->user) {
            return (bool) $this->user;
        }

        return (bool) $this->user();
    }

    /**
     * {@inheritDoc}
     */
    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }
}
