<?php

namespace App\Services;

use App\Enums\PaginationType;
use App\Models\ApiKey;
use App\Traits\Services\CanBuildPagination;
use App\Traits\Services\CanResolveModelFromId;
use Carbon\Carbon;
use Hash;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Log;
use Str;

class ApiKeyManager
{
    use CanBuildPagination;
    use CanResolveModelFromId;

    /**
     * Fetch a paginated list of API keys
     */
    public function all(): LengthAwarePaginator
    {
        $query = ApiKey::filtered()->without('user');

        return $this->buildPagination(PaginationType::LENGTH_AWARE, $query);
    }

    /**
     * Create an API Key
     */
    public function create(string $name, string|int $userId, string $description, Carbon $expiresAt, array $permissions): ApiKey
    {
        $key = Str::upper(Str::uuid());
        $createdKey = ApiKey::create([
            'name' => $name,
            'description' => $description,
            'expires_at' => $expiresAt,
            'user_id' => $userId,
            'key' => $key,
        ]);

        $createdKey->syncPermissions($permissions);
        $createdKey->rawKeyValue = $this->buildRawKey($key, $createdKey->id);

        return $createdKey;
    }

    /**
     * Retrieve a single API Key
     *
     * @throws ModelNotFoundException
     */
    public function read(int|string $id): ApiKey
    {
        return ApiKey::findOrFail($id);
    }

    /**
     * Update the records of an API Key (except the key)
     */
    public function update(ApiKey|int|string $modelOrId, string $name, string $description): ApiKey
    {
        /** @var ApiKey $apiKey */
        $apiKey = $this->retrieveModel($modelOrId, ApiKey::query());
        $apiKey->update([
            'name' => $name,
            'description' => $description,
        ]);

        return $apiKey->fresh();
    }

    /**
     * Delete a single API Key
     */
    public function destroy(ApiKey|int|string $modelOrId): bool
    {
        /** @var ApiKey $apiKey */
        $apiKey = $this->retrieveModel($modelOrId, ApiKey::query());

        return (bool) $apiKey->delete();
    }

    /**
     * Activate or Deactivate an API Key
     */
    public function setActiveStatus(Apikey|int|string $modelOrId, bool $isActive): bool
    {
        /** @var ApiKey $apiKey */
        $apiKey = $this->retrieveModel($modelOrId, ApiKey::query());

        return $apiKey->update(['active' => $isActive]);
    }

    /**
     * Check if an API Key is valid or not
     */
    public function isValid(string $key): bool
    {
        $idAndKey = explode('|', $key);
        if (count($idAndKey) !== 2) {
            Log::debug('Cannot separate the API Key ID from the raw value correctly', [
                'value' => $key,
                'method' => __METHOD__,
            ]);

            return false;
        }
        [$id, $rawKey] = $idAndKey;

        $apiKey = ApiKey::find($id);
        if (! $apiKey) {
            Log::debug('API Key ID not found', ['value' => $key, 'method' => __METHOD__]);

            return false;
        }

        if ($apiKey->isExpired()) {
            Log::debug('API Key has expired', ['value' => $key, 'method' => __METHOD__]);

            return false;
        }

        if (! $apiKey->active) {
            Log::debug('API Key is longer active', ['value' => $key, 'method' => __METHOD__]);

            return false;
        }

        if (! Hash::check($rawKey, $apiKey->key)) {
            Log::debug('API Key value is invalid', ['value' => $key, 'method' => __METHOD__]);

            return false;
        }

        return true;
    }

    /**
     * Parse the ID from the API Key
     */
    public function getIdFromKey(string $key): string|int|null
    {
        $idAndKey = $this->parseApiKey($key);

        if (! $idAndKey) {
            return null;
        }

        return $idAndKey[0];
    }

    /**
     * Parse raw value from the API Key
     */
    public function getValueFromKey(string $key): ?string
    {
        $idAndKey = $this->parseApiKey($key);

        if (! $idAndKey) {
            return null;
        }

        return $idAndKey[1];
    }

    /**
     * Build the raw key value we send back to the user
     */
    private function buildRawKey(string $key, int|string $keyId): string
    {
        return "$keyId|$key";
    }

    /**
     * @returns array Ex: ['api_key' => 'value', 'id' => 1] or null
     */
    private function parseApiKey(string $key): ?array
    {
        $idAndKey = explode('|', $key);
        if (count($idAndKey) !== 2) {
            Log::debug('Cannot separate the API Key ID and Raw value value correctly', [
                'value' => $key,
                'method' => __METHOD__,
            ]);

            return null;
        }

        return $idAndKey;
    }
}
