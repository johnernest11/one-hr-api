<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api-users'])->group(function () {
    /** V1 User resource routes */
    Route::prefix('/v1/users')->group(base_path('routes/api/users.routes.php'));

    /** V1 Auth Routes */
    Route::prefix('/v1/auth')->group(base_path('routes/api/auth.routes.php'));

    /** V1 Auth Routes */
    Route::prefix('/v1/profile')->group(base_path('routes/api/profile.routes.php'));

    /** V1 Availability Routes */
    Route::prefix('/v1/availability')->group(base_path('routes/api/availability.routes.php'));

    /** V1 Address */
    Route::prefix('v1/address')->group(base_path('routes/api/address.routes.php'));

    /** V1 Roles */
    Route::prefix('/v1')->group(base_path('routes/api/roles-permissions.routes.php'));

    /** V1 App settings */
    Route::prefix('/v1/app-settings')->group(base_path('routes/api/app-settings.routes.php'));
});

Route::middleware(['throttle:api-webhooks'])->group(function () {
    /**
     * V1 Webhook Tests
     *
     * @note Remove or Change as necessary
     */
    Route::middleware(['enabled.webhooks'])
        ->prefix('/v1/webhooks')
        ->group(base_path('routes/api/webhooks.routes.php'));
});
