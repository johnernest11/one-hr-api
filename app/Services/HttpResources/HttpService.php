<?php

namespace App\Services\HttpResources;

use App\Enums\PaginationType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\CursorPaginator;

class HttpService
{
    /**
     * Build pagination
     */
    protected function buildPagination(
        ?PaginationType $pagination,
        Builder $builder,
        ?int $limit = null,
    ): Paginator|Collection|LengthAwarePaginator|CursorPaginator {
        // If limit is not passed as a parameter, we get the limit from the request
        $limit = $limit ?? request('limit');

        // If limit is still null (no limit param found in the request), we set a default to 15
        if (! $limit) {
            $limit = 15;
        }

        return match ($pagination) {
            PaginationType::LENGTH_AWARE => $builder->paginate($limit),
            PaginationType::SIMPLE => $builder->simplePaginate($limit),
            PaginationType::CURSOR => $builder->cursorPaginate($limit),
            default => $builder->get(),
        };
    }

    /**
     * Retrieve an eloquent instance from a model or id value.
     * You can retrieve a fresh instance or load relationships
     */
    protected function getInstanceFromModelOrId(Model $model, mixed $modelOrId, bool $freshInstance = false, array $relations = []): Model
    {
        // To minimize repeating DB queries, we only rehydrate the model when needed
        if ($modelOrId instanceof $model) {
            // We retrieve data from the DB if they need a fresh instance
            if ($freshInstance) {
                return $modelOrId->fresh($relations);
            }

            // Just load the relations if they don't want a new instance but have relations to load
            if (count($relations) > 0) {
                return $modelOrId->load($relations);
            }

            // Return the same instance if a fresh instance or reloading the relationships are not needed
            else {
                return $modelOrId;
            }
        }

        return $model::with($relations)->findOrFail($modelOrId);
    }
}
