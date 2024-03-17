<?php

namespace App\Services\ApiKey;

use App\Models\ApiKey;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ApiKeyServiceInterface
{
    /** Fetch a paginated list of API keys */
    public function all(): LengthAwarePaginator;

    /** Create an API Key */
    public function create(array $apiKeyInfo): ApiKey;

    /** Retrieve a single API Key */
    public function read(int|string $id): ApiKey;

    /** Delete an API Key */
    public function destroy(ApiKey|int|string $modelOrId): bool;

    /** Set if the API Key should be active or not */
    public function setActiveStatus(ApiKey|int|string $modelOrId, bool $isActive): bool;
}
