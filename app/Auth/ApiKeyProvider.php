<?php

namespace App\Auth;

use App\Models\ApiKey;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Hash;

class ApiKeyProvider implements UserProvider
{
    /**
     * {@inheritDoc}
     */
    public function retrieveById($identifier): ?ApiKey
    {
        $apiKey = ApiKey::active()->where('id', $identifier);

        if ($apiKey && ! $apiKey->isExpired()) {
            return $apiKey;
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveByToken($identifier, $token): ?ApiKey
    {
        $key = Hash::make($token);

        return ApiKey::where('id', $identifier)->where('key', $key)->first();
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
