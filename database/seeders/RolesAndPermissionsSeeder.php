<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Enums\WebhookPermission;
use App\Models\Permission;
use App\Models\Role;
use ConversionHelper;
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
        Permission::create(['name' => PermissionEnum::VIEW_POSITIONS, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::VIEW_FUNDS, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::VIEW_OFFICES, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::VIEW_DIVISIONS, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::VIEW_SECTION_OR_UNITS, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::VIEW_COUNTRIES, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::VIEW_SALARY_GRADES, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::VIEW_PROGRAMS, 'guard_name' => 'token']);

        // Employee permissions
        Permission::create(['name' => PermissionEnum::VIEW_EMPLOYEE_PDS, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::UPDATE_EMPLOYEE_PDS, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::DELETE_EMPLOYEE_PDS, 'guard_name' => 'token']);

        /** @var Role $standardRole */
        $standardRole = Role::create(['name' => RoleEnum::STANDARD_USER, 'guard_name' => 'token']);
        $standardRole->givePermissionTo(Permission::all());

        /** @var Role $sectionHeadRole */
        $sectionHeadRole = Role::create(['name' => RoleEnum::SECTION_HEAD, 'guard_name' => 'token']);
        $sectionHeadRole->givePermissionTo(Permission::all());

        /** @var Role $divisionHeadRole */
        $divisionHeadRole = Role::create(['name' => RoleEnum::DIVISION_HEAD, 'guard_name' => 'token']);
        $divisionHeadRole->givePermissionTo(Permission::all());

        // Admin Permissions
        Permission::create(['name' => PermissionEnum::CREATE_USERS, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::UPDATE_USERS, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::DELETE_USERS, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::VIEW_USERS, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::VIEW_USER_ROLES, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::VIEW_PERMISSIONS, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::UPDATE_APP_SETTINGS, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::GENERATE_READ_UPDATE_QR_CODE, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::VERIFY_QR_CODE, 'guard_name' => 'token']);

        /** @var Role $hrPasAdminRole */
        $hrPasAdminRole = Role::create(['name' => RoleEnum::HR_PAS_ADMIN, 'guard_name' => 'token']);
        $hrPasAdminRole->givePermissionTo(Permission::all());

        Permission::create(['name' => PermissionEnum::CREATE_ITEMS, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::UPDATE_ITEMS, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::VIEW_ITEMS, 'guard_name' => 'token']);
        Permission::create(['name' => PermissionEnum::CREATE_EMPLOYEE_PDS, 'guard_name' => 'token']);
        /** @var Role $hrPpmsAdminRole */
        $hrPpmsAdminRole = Role::create(['name' => RoleEnum::HR_PPMS_ADMIN, 'guard_name' => 'token']);
        $hrPpmsAdminRole->givePermissionTo(Permission::whereNotIn('name', [PermissionEnum::GENERATE_READ_UPDATE_QR_CODE->value])->get()); // PPMS cannot generate QR code for employees

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
