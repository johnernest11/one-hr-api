<?php

namespace App\Guards;

use App\Interfaces\Services\Authentication\AuthTokenManager;
use App\Interfaces\Services\Authentication\PersistentAuthTokenManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;

class MultiAuthGuard implements Guard
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

        if (config('auth.mechanism.sanctum_enabled')) {
            if ($token) {
                $sanctumService = resolve(PersistentAuthTokenManager::class);

                return $sanctumService->tokenIsValid($token);
            }
        }

        if (config('auth.mechanism.jwt_enabled')) {
            if ($token) {
                $sanctumService = resolve(AuthTokenManager::class);

                return $sanctumService->tokenIsValid($token);
            }
        }

        /** TODO: Implement Basic Auth Check */

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
        $token = request()->bearerToken();

        if (! is_null($this->user)) {
            return $this->user;
        }

        if (config('auth.mechanism.sanctum_enabled')) {
            if ($token) {
                $sanctumService = resolve(PersistentAuthTokenManager::class);
                $this->user = $sanctumService->getTokenOwner($token);

                return $this->user;
            }
        }

        if (config('auth.mechanism.jwt_enabled')) {
            if ($token) {
                $sanctumService = resolve(AuthTokenManager::class);
                $this->user = $sanctumService->getTokenOwner($token);

                return $this->user;
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
                $sanctumService = resolve(PersistentAuthTokenManager::class);

                return $sanctumService->getTokenOwner($token)?->id;
            }
        }

        if (config('auth.mechanism.jwt_enabled')) {
            if ($token) {
                $sanctumService = resolve(AuthTokenManager::class);

                return $sanctumService->getTokenOwner($token)?->id;
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
