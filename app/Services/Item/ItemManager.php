<?php

namespace App\Services\Item;

use App\Enums\PaginationType;
use App\Models\Item;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\CursorPaginator;

interface ItemManager
{
    /**
     * Fetch all Item Numbers
     */
    public function all(): LengthAwarePaginator;

    /**
     * Create an Item
     */
    public function create(array $itemInfo): Item;

    /**
     * Fetch a single Item
     */
    public function read(Item|int $item): Item;

    /**
     * Update an Item
     */
    public function update(Item $item, array $newItemInfo): Item;

    /**
     * Search for Item/s
     */
    public function search(
        string $term,
        ?PaginationType $pagination = null
    ): Collection|Paginator|LengthAwarePaginator|CursorPaginator;
}
