<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class TagStandardUserRoleSeeder extends Seeder
{
    public function run(): void
    {
        $count = 0;
        // 1. Get the role from default database
        $role = Role::where('name', RoleEnum::STANDARD_USER)->first();

        if (! $role) {
            $this->command->error("Role '".RoleEnum::STANDARD_USER."' not found in default database.");

            return;
        }
        // 2. Get all users from one_account connection
        $users = User::all();

        foreach ($users as $user) {
            // Check if the user already has this role
            if (! $user->hasRole(RoleEnum::STANDARD_USER)) {
                // Assign the role (works across databases)
                $user->assignRole($role);
                $count++;
            }
        }

        $this->command->info("Tagged {$count} users with 'standard_user' role.");
    }
}
