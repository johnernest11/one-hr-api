<?php

namespace App\Auth;

use App\Models\ApiKey;
use App\Services\ApiKeyManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

class ApiKeyGuard implements Guard
{
    private Request $request;

    private UserProvider $apiKeyProvider;

    private ?Authenticatable $apiKey;

    private ApiKeyManager $apiKeyService;

    const HEADER_NAME = 'X-API-KEY';

    public function __construct(Request $request, UserProvider $provider)
    {
        $this->request = $request;
        $this->apiKeyProvider = $provider;
        $this->apiKey = null;
        $this->apiKeyService = resolve(ApiKeyManager::class);
    }

    /**
     * {@inheritDoc}
     */
    public function check(): bool
    {
        $key = $this->request->header(static::HEADER_NAME);
        if (! $key) {
            return false;
        }

        $isValid = $this->apiKeyService->isValid($key);
        if ($isValid) {
            $id = $this->apiKeyService->getIdFromKey($key);
            $this->apiKey = $this->apiKeyProvider->retrieveById($id);

            return true;
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
    public function user(): ?Authenticatable
    {
        if (! is_null($this->apiKey)) {
            return $this->apiKey;
        }

        $key = $this->request->header(static::HEADER_NAME);
        if (! $key) {
            return null;
        }

        $id = $this->apiKeyService->getIdFromKey($key);

        /** @var ApiKey $foundApiKey */
        $foundApiKey = $this->apiKeyProvider->retrieveById($id);
        if (! $foundApiKey) {
            return null;
        }

        $this->apiKey = $foundApiKey;

        return $this->apiKey;
    }

    /**
     * {@inheritDoc}
     */
    public function id(): mixed
    {
        if (! is_null($this->apiKey)) {
            return $this->apiKey->id;
        }

        $key = $this->request->header(static::HEADER_NAME);
        if (! $key) {
            return null;
        }

        $id = $this->apiKeyService->getIdFromKey($key);

        /** @var ApiKey $foundApiKey */
        $foundApiKey = $this->apiKeyProvider->retrieveById($id);
        if (! $foundApiKey) {
            return null;
        }

        $this->apiKey = $foundApiKey;

        return $this->apiKey->id;
    }

    /**
     * {@inheritDoc}
     */
    public function validate(array $credentials = [])
    {
        /** @Note API Keys don't implement this functionality */
    }

    /**
     * {@inheritDoc}
     */
    public function hasUser(): bool
    {
        if ($this->apiKey) {
            return (bool) $this->apiKey;
        }

        return (bool) $this->user();
    }

    /**
     * {@inheritDoc}
     */
    public function setUser(Authenticatable $user): void
    {
        $this->apiKey = $user;
    }
}
