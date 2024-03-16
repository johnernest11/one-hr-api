<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

class ApiKeyGuard implements Guard
{
    private Request $request;

    private UserProvider $apiKeyProvider;

    public function __construct(Request $request, UserProvider $provider)
    {
        $this->request = $request;
        $this->apiKeyProvider = $provider;
    }

    /**
     * {@inheritDoc}
     */
    public function check()
    {
        // TODO: Implement check() method.
    }

    /**
     * {@inheritDoc}
     */
    public function guest()
    {
        // TODO: Implement guest() method.
    }

    /**
     * {@inheritDoc}
     */
    public function user()
    {
        // TODO: Implement user() method.
    }

    /**
     * {@inheritDoc}
     */
    public function id()
    {
        // TODO: Implement id() method.
    }

    /**
     * {@inheritDoc}
     */
    public function validate(array $credentials = [])
    {
        // TODO: Implement validate() method.
    }

    /**
     * {@inheritDoc}
     */
    public function hasUser()
    {
        // TODO: Implement hasUser() method.
    }

    /**
     * {@inheritDoc}
     */
    public function setUser(Authenticatable $user)
    {
        // TODO: Implement setUser() method.
    }
}
