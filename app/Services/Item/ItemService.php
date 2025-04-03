<?php

namespace App\Services\Item;

use App\Enums\PaginationType;
use App\Models\Item;
use App\Traits\Services\CanBuildPagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ItemService implements ItemManager
{
    use CanBuildPagination;

    public const MAX_TRANSACTION_DEADLOCK_ATTEMPTS = 5;

    /** {@inheritDoc} */
    public function all(): LengthAwarePaginator
    {
        /** @var Builder $item */
        $query = Item::filtered();

        return $this->buildPagination(PaginationType::LENGTH_AWARE, $query);
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $itemInfo): Item
    {
        return DB::transaction(function () use ($itemInfo) {
            $item = Item::create($itemInfo);

            return $item;
        }, self::MAX_TRANSACTION_DEADLOCK_ATTEMPTS);
    }

    /** {@inheritDoc} */
    public function read(Item $item): Item
    {
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
}
