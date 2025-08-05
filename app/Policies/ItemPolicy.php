<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Item;
use App\Models\User;

class ItemPolicy
{
    /**
     * Determine whether the user can view the item.
     * Standard Users should be able to view their own item.
     * PPMS admin can view all. This is already established in roles and permissions.
     */
    public function viewItem(User $user, Item $item): bool
    {
        if ($user && $user->can(Permission::VIEW_ITEMS->value)) {
            return true;
        }

        return $user->userProfile->individualBasicDetail->employee->item->id === $item->id;
    }
}
