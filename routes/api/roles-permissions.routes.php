<?php

use App\Enums\Permission;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;

Route::middleware(['auth:token', 'verified.api'])->controller(RoleController::class)
    ->name('roles.')->group(function () {
        /** @uses RoleController::view */
        Route::middleware(['permission:'.Permission::VIEW_USER_ROLES->value])
            ->get('roles', 'index')->name('index');
    });

Route::middleware(['auth:token', 'verified.api'])->controller(PermissionController::class)
    ->name('permissions.')->group(function () {
        /** @uses PermissionController::view */
        Route::middleware(['permission:'.Permission::VIEW_PERMISSIONS->value])
            ->get('permissions', 'index')->name('index');
    });
