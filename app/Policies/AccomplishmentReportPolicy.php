<?php

namespace App\Policies;

use App\Models\AccomplishmentReport;
use App\Models\User;

class AccomplishmentReportPolicy
{
    /**
     * Only the user's own ARs can be viewed.
     */
    public function view(User $user, AccomplishmentReport $accomplishmentReport): bool
    {
        return $user->id === $accomplishmentReport->userProfile->user_id;
    }

    /**
     * Only the user's own ARs can be updated.
     */
    public function update(User $user, AccomplishmentReport $accomplishmentReport): bool
    {
        return $user->id === $accomplishmentReport->userProfile->user_id;
    }
}
