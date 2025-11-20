<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class TagStandardUserRoleSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Get the role from default database
        $role = Role::where('name', 'standard_user')->first();

        if (! $role) {
            $role = Role::create([
                'name' => 'standard_user',
                'guard_name' => 'token', // adjust if your users use a different guard
            ]);
            $this->command->info("Created 'standard_user' role with ID {$role->id}");
        } else {
            $this->command->info("'standard_user' role already exists with ID {$role->id}");
        }

        // 2. Get all users from one_account connection
        $users = User::all(); // User model has protected $connection = 'one_account'

        $count = 0;

        foreach ($users as $user) {
            // assignRole works across databases because it resolves the Role model
            if (! $user->hasRole('standard_user')) {
                $user->assignRole($role);
                $count++;
            }
        }

        $this->command->info("Tagged {$count} users with 'standard_user' role.");
    }
}
