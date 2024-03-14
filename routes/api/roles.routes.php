<?php

use App\Enums\Permission;
use App\Http\Controllers\RoleController;

Route::middleware(['auth:token', 'verified.api'])->controller(RoleController::class)
    ->name('roles.')->group(function () {
        /** @uses ProfileController::view */
        Route::middleware(['permission:'.Permission::VIEW_USER_ROLES->value])
            ->get('', 'index')->name('index');
    });
