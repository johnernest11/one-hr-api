<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Models\Permission;
use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UpdateQRPermissionSeeder extends CiCdCompliantSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissionName = PermissionEnum::GENERATE_READ_UPDATE_QR_CODE->value;

        $permission = Permission::firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'token',
        ]);

        $targetRoles = [
            RoleEnum::STANDARD_USER->value,
            RoleEnum::HR_PPMS_ADMIN->value,
        ];

        foreach ($targetRoles as $roleName) {
            $role = Role::where('name', $roleName)
                ->where('guard_name', 'token')
                ->first();

            if (! $role) {
                $this->command->error("Role '{$roleName}' not found. Skipping.");

                continue;
            }

            if (! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
                $this->command->info("Assigned '{$permissionName}' to role '{$roleName}'.");
            } else {
                $this->command->warn("Role '{$roleName}' already has '{$permissionName}'. Skipping.");
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function tableName(): string
    {
        return app(Role::class)->getTable();
    }

    public function shouldRun(): bool
    {
        return true;
    }
}
