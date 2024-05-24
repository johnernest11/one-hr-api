<?php

namespace App\Services\User;

use App\Models\User;

interface UserCredentialManager
{
    /**
     * Change a user's password
     */
    public function updatePassword(User|int|string $modelOrId, string $newPassword, string $oldPassword): ?User;

    /**
     * Fetch a user with the email and password credentials
     */
    public function getUserViaEmailAndPassword(string $email, string $password): ?User;

    /**
     * Fetch the user with the mobile number and password credentials
     */
    public function getUserViaMobileNumberAndPassword(string $mobileNumber, string $password): ?User;
}
