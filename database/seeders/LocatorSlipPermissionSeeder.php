<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Models\Permission;
use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LocatorSlipPermissionSeeder extends CiCdCompliantSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $newPermissionName = PermissionEnum::CRUD_LOCATOR_SLIP->value;

        $newPermission = Permission::firstOrCreate(
            ['name' => $newPermissionName, 'guard_name' => 'token'],
        );

        $excludedRoles = [RoleEnum::TIME_LOGGER->value];
        $rolesToAssign = Role::where('guard_name', 'token')->whereNotIn('name', $excludedRoles)->get();

        foreach ($rolesToAssign as $role) {
            if (! $role->hasPermissionTo($newPermission)) {
                $role->givePermissionTo($newPermission);
                $this->command->info("Assigned permission '{$newPermissionName}' to role '{$role->name}'.");
            } else {
                $this->command->warn("Role '{$role->name}' already has permission '{$newPermissionName}'. Skipping.");
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
