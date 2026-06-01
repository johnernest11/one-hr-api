<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Models\Permission;
use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LocatorActivityPermissionSeeder extends CiCdCompliantSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $newPermissionName = PermissionEnum::VIEW_LOCATOR_ACTIVITIES->value;

        $newPermission = Permission::firstOrCreate(
            ['name' => $newPermissionName, 'guard_name' => 'token'],
        );

        $rolesToAssign = Role::where('guard_name', 'token')->get();

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
