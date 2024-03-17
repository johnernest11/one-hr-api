<?php

namespace App\Services\ApiKey;

use App\Enums\PaginationType;
use App\Models\ApiKey;
use App\Traits\Services\CanBuildPagination;
use App\Traits\Services\CanResolveModelViaId;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Str;

class ApiKeyService implements ApiKeyServiceInterface
{
    use CanBuildPagination;
    use CanResolveModelViaId;

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
        $query = $this->model::filtered()->without('user.roles');

        return $this->buildPagination(PaginationType::LENGTH_AWARE, $query);
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $apiKeyInfo): ApiKey
    {
        return $this->model::create([
            'name' => $apiKeyInfo['name'],
            'description' => $apiKeyInfo['description'],
            'expires_at' => Carbon::parse($apiKeyInfo['expires_at'])->endOfDay(),
            'user_id' => $apiKeyInfo['user_id'],
            'key' => Str::uuid(),
        ]);
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
     */
    public function destroy(ApiKey|int|string $modelOrId): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function setActiveStatus(Apikey|int|string $modelOrId, bool $isActive): bool
    {
        return true;
    }
}
