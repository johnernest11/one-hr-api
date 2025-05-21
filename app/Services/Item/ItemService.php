<?php

namespace App\Services\Item;

use App\Enums\PaginationType;
use App\Models\Item;
use App\Traits\Services\CanBuildPagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

class ItemService implements ItemManager
{
    use CanBuildPagination;

    public const MAX_TRANSACTION_DEADLOCK_ATTEMPTS = 5;

    private Item $model;

    public function __construct(Item $model)
    {
        $this->model = $model;

    }

    /** {@inheritDoc} */
    public function all(): LengthAwarePaginator
    {
        /** @var Builder $item */
        $query = $this->model->filtered();

        return $this->buildPagination(PaginationType::LENGTH_AWARE, $query);
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $itemInfo): Item
    {
        return DB::transaction(function () use ($itemInfo) {
            $item = $this->model->create($itemInfo);

            return $item;
        }, self::MAX_TRANSACTION_DEADLOCK_ATTEMPTS);
    }

    /** {@inheritDoc} */
    public function read(Item|int $item): Item
    {
        // check if Item or int
        if ($item instanceof Item) {
            $item = $this->model->findOrFail($item->id);
        } else {
            $item = $this->model->findOrFail($item);
        }

        return $item;
    }

    /**
     * {@inheritDoc}
     */
    public function update(Item $item, array $newItemInfo): Item
    {
        return DB::transaction(function () use ($item, $newItemInfo) {

            $item->update($newItemInfo);

            return $item->fresh();
        }, self::MAX_TRANSACTION_DEADLOCK_ATTEMPTS);
    }

    /**
     * {@inheritDoc}
     */
    public function search(
        string $term,
        ?PaginationType $pagination = null
    ): Collection|Paginator|LengthAwarePaginator|CursorPaginator {
        /** @var Builder $item */
        $query = $this->model->filtered();
        $items = $query->where('number', 'like', "%$term%");

        return $this->buildPagination($pagination, $items);
    }
}
