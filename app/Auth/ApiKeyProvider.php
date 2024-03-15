<?php

namespace App\Auth;

use App\Models\ApiKey;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class ApiKeyProvider implements UserProvider
{
    /**
     * {@inheritDoc}
     */
    public function retrieveById($identifier): ?ApiKey
    {
        $apiKey = ApiKey::find($identifier);

        if ($apiKey && $apiKey->isActive()) {
            return $apiKey;
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveByToken($identifier, $token)
    {
        // TODO: Implement retrieveByToken() method.
    }

    /**
     * {@inheritDoc}
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        // TODO: Implement updateRememberToken() method.
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveByCredentials(array $credentials)
    {
        // TODO: Implement retrieveByCredentials() method.
    }

    /**
     * {@inheritDoc}
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        // TODO: Implement validateCredentials() method.
    }
}
