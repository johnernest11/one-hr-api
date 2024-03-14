<?php

namespace App\Guards;

use App\Interfaces\Services\Authentication\AuthTokenManager;
use App\Interfaces\Services\Authentication\PersistentAuthTokenManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;

class MultiTokenAuthGuard implements Guard
{
    private ?Authenticatable $user;

    public function __construct()
    {
        $this->user = null;
    }

    /**
     * {@inheritDoc}
     */
    public function check(): bool
    {
        $token = request()->bearerToken();
        $sanctumVerified = false;
        $jwtVerified = false;

        if (config('auth.mechanism.sanctum_enabled')) {
            if ($token) {
                $sanctumAuthService = resolve(PersistentAuthTokenManager::class);
                $sanctumVerified = $sanctumAuthService->tokenIsValid($token);
            }
        }

        if (config('auth.mechanism.jwt_enabled')) {
            if ($token) {
                $jwtAuthService = resolve(AuthTokenManager::class);
                $jwtVerified = $jwtAuthService->tokenIsValid($token);
            }
        }

        /** TODO: Implement Basic Auth Check */

        return $sanctumVerified || $jwtVerified;
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
        $token = request()->bearerToken();

        if (! is_null($this->user)) {
            return $this->user;
        }

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

        return $this->user;
    }

    /**
     * {@inheritDoc}
     */
    public function id(): mixed
    {
        if (! is_null($this->user)) {
            return $this->user->id;
        }

        $token = request()->bearerToken();

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
    public function validate(array $credentials = [])
    {
        /** We support a credentials validation for Basic Authentication */
        // TODO: Implement validate() method.
    }

    /**
     * {@inheritDoc}
     */
    public function hasUser(): bool
    {
        return $this->check();
    }

    /**
     * {@inheritDoc}
     */
    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }
}
