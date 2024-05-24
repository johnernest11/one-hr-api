<?php

namespace App\Services\User;

use App\Enums\PaginationType;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\CursorPaginator;

interface UserAccountManager
{
    /**
     * Fetch a list of users
     */
    public function all(): LengthAwarePaginator;

    /**
     * Create a new user
     */
    public function create(array $userInfo): User;

    /**
     * Update an existing user
     */
    public function update(User|int|string $modelOrId, array $newUserInfo): User;

    /**
     * Fetch a single User
     */
    public function read(int|string $id, array $relationships): User;

    /**
     * Delete a single User
     */
    public function destroy(User|int|string $modelOrId): User;

    /**
     * Search for a user
     */
    public function search(
        string $term,
        ?PaginationType $pagination = null
    ): Collection|Paginator|LengthAwarePaginator|CursorPaginator;
}
