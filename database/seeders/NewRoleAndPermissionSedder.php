<?php

namespace Database\Seeders;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use App\Enums\WebhookPermission;
use App\Models\Permission;
use App\Models\Role;
use ConversionHelper;
use Spatie\Permission\PermissionRegistrar;

class NewRoleAndPermissionSedder extends CiCdCompliantSeeder
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

        /** PAS Permission */
        $viewItemsPermission = Permission::firstOrCreate([
            'name' => PermissionEnum::VIEW_ITEMS->value,
            'guard_name' => 'token',
        ]);

        /** @var Role $hrPasAdminRole */
        $hrPasAdminRole = Role::firstOrCreate([
            'name' => RoleEnum::HR_PAS_ADMIN->value,
            'guard_name' => 'token',
        ]);

        $hrPasAdminRole->givePermissionTo($viewItemsPermission);

        /**
         * Permissions for the API Keys.
         * We set basic test permissions for webhooks. Add more permissions depending on the project.
         */
        foreach (ConversionHelper::enumToArray(WebhookPermission::class) as $permissions) {
            Permission::firstOrCreate([
                'name' => $permissions,
                'guard_name' => 'api_key',
            ]);
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
        return true;
    }
}
