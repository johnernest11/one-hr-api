<?php

namespace App\Services\ApiKey;

use App\Models\ApiKey;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

interface ApiKeyServiceInterface
{
    /** Fetch a paginated list of API keys */
    public function all(): LengthAwarePaginator;

    /** Create an API Key */
    public function create(string $name, string|int $userId, string $description, Carbon $expiresAt): ApiKey;

    /**
     * Retrieve a single API Key
     *
     * @throws ModelNotFoundException
     */
    public function read(int|string $id): ApiKey;

    /** Update the records of an API Key (except the key) */
    public function update(ApiKey|int|string $modelOrId, string $name, string $description): ApiKey;

    /** Delete an API Key */
    public function destroy(ApiKey|int|string $modelOrId): bool;

    /** Set if the API Key should be active or not */
    public function setActiveStatus(ApiKey|int|string $modelOrId, bool $isActive): bool;

    /** Check if the API Key is still valid */
    public function isValid(string $key): bool;

    /** Parse the ID from the API Key */
    public function getIdFromKey(string $key);

    /** Parse raw value from the API Key */
    public function getValueFromKey(string $key);
}
