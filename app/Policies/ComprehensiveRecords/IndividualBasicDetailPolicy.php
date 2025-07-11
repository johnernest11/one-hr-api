<?php

namespace App\Policies\ComprehensiveRecords;

use App\Enums\Role;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\User;

class IndividualBasicDetailPolicy
{
    // Roles and what they can do:
    // 1. PPMS - Create, Update and View
    // 2. PAS - Update and View
    // 3. Standard User - Update and View own record

    /**
     * Determine whether the user can view the model.
     * HR PAS admin can view all records.
     */
    public function view(User $user, IndividualBasicDetail $individualBasicDetail): bool
    {
        if ($user->hasAnyRole([Role::HR_PPMS_ADMIN->value, Role::ADMIN->value, Role::HR_PAS_ADMIN->value, Role::SUPER_USER->value])) {
            return true;
        }

        return $user->id === $individualBasicDetail->userProfile->user_id;
    }

    /**
     * Check if an individual reaches the maximum number of references
     */
    public function maximumReferencesReached(IndividualBasicDetail $individualBasicDetail, array $request): bool
    {
        $maxReferences = 3; // Maximum number of references
        $refsCount = $individualBasicDetail->individualReference()->count();
        $incomingRefsCount = 0;

        // Count number of incoming individual_reference request without ids.
        // Request without ids will create a new record.
        // Request with ids will not.
        foreach ($request['individual_reference'] as $reference) {
            if (! array_key_exists('id', $reference)) {
                $incomingRefsCount++;
            }
        }

        if (($refsCount + $incomingRefsCount) > $maxReferences) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     *
     * Will also check if the maximum number of references is reached.
     */
    public function update(User $user, IndividualBasicDetail $individualBasicDetail, array $request): bool
    {
        // Check if maximum reference is reached.
        // Check first if individual_reference is part of the request.
        if (isset($request['individual_reference'])) {
            if ($this->maximumReferencesReached($individualBasicDetail, $request)) {
                return false;
            }

        }
        if ($user->hasAnyRole([Role::HR_PPMS_ADMIN->value, Role::ADMIN->value, Role::HR_PAS_ADMIN->value, Role::SUPER_USER->value])) {
            return true; // give permission to update records if user's role is HR PPMS admin or HR PAS admin
        }

        return $user->id === $individualBasicDetail->userProfile->user_id;
    }
}
