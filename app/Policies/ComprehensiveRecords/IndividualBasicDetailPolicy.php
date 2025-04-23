<?php

namespace App\Policies\ComprehensiveRecords;

use App\Enums\Role;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\User;

//@todo UPDATE POLICY
class IndividualBasicDetailPolicy
{
    /**
     * Determine whether the user can view the model.
     * HR PAS admin can view all records.
     */
    public function view(User $user, IndividualBasicDetail $individualBasicDetail): bool
    {
        if ($user->hasRole(Role::HR_PAS_ADMIN->value)) {
            return true; // give permission to view records if user's role is HR admin PAS
        }

        return $user->id === $individualBasicDetail->personnel_id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, IndividualBasicDetail $individualBasicDetail): bool
    {
        return $user->id === $individualBasicDetail->personnel_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, IndividualBasicDetail $individualBasicDetail): bool
    {
        return $user->id === $individualBasicDetail->personnel_id;
    }
}
