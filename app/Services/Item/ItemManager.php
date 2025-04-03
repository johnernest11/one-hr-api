<?php

namespace App\Services\Item;

use App\Models\Item;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
    public function read(Item $item): Item;

    /**
     * Update an Item
     */
    public function update(Item $item, array $newItemInfo): Item;
}
