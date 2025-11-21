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
        // 2. Get only users WITHOUT the standard user role
        $users = User::whereDoesntHave('roles', function ($q) {
            $q->where('name', RoleEnum::STANDARD_USER);
        })->get();

        foreach ($users as $user) {
            $user->assignRole($role);
            $count++;
        }

        $this->command->info("Tagged {$count} users with 'standard_user' role.");
    }
}
