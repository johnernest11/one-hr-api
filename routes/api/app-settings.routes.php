<?php

use App\Enums\Permission;
use App\Http\Controllers\AppSettingsController;

Route::controller(AppSettingsController::class)->name('app-settings.')->group(function () {
    /** @uses AppSettingsController::store */
    Route::middleware([
        'auth:token',
        'verified.api',
        'permission:'.Permission::UPDATE_APP_SETTINGS->value,
    ])
        ->post('', 'store')->name('store');

    /** @uses AppSettingsController::index */
    Route::get('', 'index')->name('index');
});
