<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Enums\WebhookPermission;
use ConversionHelper;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends CiCdCompliantSeeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /**
         * Reset cached roles and permissions
         *
         * @see https://spatie.be/docs/laravel-permission/v6/advanced-usage/seeding
         */
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Standard user permissions
        Permission::create(['name' => PermissionEnum::VIEW_PROFILE, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::UPDATE_PROFILE, 'guard_name' => 'token']);
        /** @var Role $standardRole */
        $standardRole = Role::create(['name' => RoleEnum::STANDARD_USER, 'guard_name' => 'token']);
        $standardRole->givePermissionTo(Permission::all());

        // Admin Permissions
        Permission::create(['name' => PermissionEnum::CREATE_USERS, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::UPDATE_USERS, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::DELETE_USERS, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::VIEW_USERS, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::VIEW_USER_ROLES, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::VIEW_PERMISSIONS, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::UPDATE_APP_SETTINGS, 'guard_name' => 'token']);
        /** @var Role $adminRole */
        $adminRole = Role::create(['name' => RoleEnum::ADMIN, 'guard_name' => 'token']);
        $adminRole->givePermissionTo(Permission::all());

        // System Support Permissions
        $notification_per = Permission::create(['name' => PermissionEnum::RECEIVE_SYSTEM_ALERTS, 'guard_name' => 'token']);
        /** @var Role $systemSupport */
        $systemSupport = Role::create(['name' => RoleEnum::SYSTEM_SUPPORT, 'guard_name' => 'token']);
        $systemSupport->givePermissionTo($notification_per);

        /**
         * Superuser role. We allow all permissions through here
         *
         * @see \App\Providers\AuthServiceProvider
         *
         * @var Role $superUserRole
         */
        Role::create(['name' => RoleEnum::SUPER_USER, 'guard_name' => 'token']);

        /**
         * Permissions for the API Keys.
         * We set basic test permissions for webhooks. Add more permissions depending on the project.
         */
        foreach (ConversionHelper::enumToArray(WebhookPermission::class) as $permissions) {
            Permission::create(['name' => $permissions, 'guard_name' => 'api_key']);
        }

        /**
         * Reset cached roles and permissions
         *
         * @see https://spatie.be/docs/laravel-permission/v6/advanced-usage/seeding
         */
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function tableName(): string
    {
        return app(Role::class)->getTable();
    }

    /** {@inheritDoc} */
    public function shouldRun(): bool
    {
        return $this->tableIsEmpty();
    }
}
