<?php

namespace App\Policies;

use App\Models\CompensatoryReport;
use App\Models\User;

class CompensatoryPolicy
{
    /**
     * Only the user's own CTDOs can be viewed.
     */
    public function view(User $user, CompensatoryReport $compensatoryReport): bool
    {
        return $user->id === $compensatoryReport->userProfile->user_id;
    }
}
