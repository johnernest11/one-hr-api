<?php

namespace App\Auth;

use App\Models\ApiKey;
use App\Services\ApiKey\ApiKeyServiceInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;

class ApiKeyProvider implements UserProvider
{
    private ApiKeyServiceInterface $apiKeyService;

    public function __construct(ApiKeyServiceInterface $apiKeyService)
    {
        $this->apiKeyService = $apiKeyService;
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveById($identifier): ?ApiKey
    {
        try {
            $apiKey = $this->apiKeyService->read($identifier);
        } catch (ModelNotFoundException) {
            return null;
        }

        if (! $apiKey->isExpired()) {
            return $apiKey;
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveByToken($identifier, $token): ?ApiKey
    {
        try {
            $apiKey = $this->apiKeyService->read($identifier);
        } catch (ModelNotFoundException) {
            return null;
        }

        $isValid = Hash::check($token, $apiKey->key);
        if (! $isValid) {
            return null;
        }

        return $apiKey;
    }

    /**
     * {@inheritDoc}
     */
    public function updateRememberToken(Authenticatable $user, $token): ?Authenticatable
    {
        /** @Note API Keys don't implement this functionality */
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        /** @Note API Keys don't implement this functionality */
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function validateCredentials(Authenticatable $user, array $credentials): ?Authenticatable
    {
        /** @Note API Keys don't implement this functionality */
        return null;
    }
}
