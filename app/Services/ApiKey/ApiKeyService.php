<?php

namespace App\Services\ApiKey;

use App\Enums\PaginationType;
use App\Models\ApiKey;
use App\Traits\Services\CanBuildPagination;
use App\Traits\Services\CanResolveModelFromId;
use Carbon\Carbon;
use Hash;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Log;
use Str;

class ApiKeyService implements ApiKeyServiceInterface
{
    use CanBuildPagination;
    use CanResolveModelFromId;

    private ApiKey $model;

    public function __construct(ApiKey $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritDoc}
     */
    public function all(): LengthAwarePaginator
    {
        $query = $this->model::filtered()->without('user');

        return $this->buildPagination(PaginationType::LENGTH_AWARE, $query);
    }

    /**
     * {@inheritDoc}
     */
    public function create(string $name, string|int $userId, string $description, Carbon $expiresAt): ApiKey
    {
        $key = Str::upper(Str::uuid());
        $createdKey = $this->model::create([
            'name' => $name,
            'description' => $description,
            'expires_at' => $expiresAt,
            'user_id' => $userId,
            'key' => $key,
        ]);

        $createdKey->rawKeyValue = $this->buildRawKey($key, $createdKey->id);

        return $createdKey;
    }

    /** Build the raw key value we send back to the user */
    private function buildRawKey(string $key, int|string $keyId): string
    {
        return "$keyId|$key";
    }

    /**
     * {@inheritDoc}
     */
    public function read(int|string $id): ApiKey
    {
        return $this->model::findOrFail($id);
    }

    /**
     * {@inheritDoc}
     *
     * @param  array  $updatedInfo
     */
    public function update(ApiKey|int|string $modelOrId, string $name, string $description): ApiKey
    {
        /** @var ApiKey $apiKey */
        $apiKey = $this->retrieveModel($modelOrId);
        $apiKey->update([
            'name' => $name,
            'description' => $description,
        ]);

        return $apiKey->fresh();
    }

    /**
     * {@inheritDoc}
     */
    public function destroy(ApiKey|int|string $modelOrId): bool
    {
        /** @var ApiKey $apiKey */
        $apiKey = $this->retrieveModel($modelOrId);

        return (bool) $apiKey->delete();
    }

    /**
     * {@inheritDoc}
     */
    public function setActiveStatus(Apikey|int|string $modelOrId, bool $isActive): bool
    {
        /** @var ApiKey $apiKey */
        $apiKey = $this->retrieveModel($modelOrId);

        return $apiKey->update(['active' => $isActive]);
    }

    public function isValid(string $key): bool
    {
        $idAndKey = explode('|', $key);
        if (count($idAndKey) !== 2) {
            Log::debug('Cannot separate the API Key ID and Raw value value correctly', [
                'value' => $key,
                'method' => __METHOD__,
            ]);

            return false;
        }
        [$id, $rawKey] = $idAndKey;

        $apiKey = $this->model::find($id);
        if (! $apiKey) {
            Log::debug('API Key ID not found', ['value' => $key, 'method' => __METHOD__]);

            return false;
        }

        if ($apiKey->isExpired()) {
            Log::debug('API Key has expired', ['value' => $key, 'method' => __METHOD__]);

            return false;
        }

        if (! Hash::check($rawKey, $apiKey->key)) {
            Log::debug('API Key value is invalid', ['value' => $key, 'method' => __METHOD__]);

            return false;
        }

        return true;
    }
}
