<?php

use App\Enums\Permission;
use App\Http\Controllers\Libraries\FundSourceController;
use App\Http\Controllers\Libraries\PositionController;

// Positions
Route::prefix('positions')->controller(PositionController::class)->name('positions.')->group(function () {
    /** @uses PositionController::fetch */
    Route::middleware(['permission:'.Permission::VIEW_POSITIONS->value])
        ->get('', 'fetch')->name('index');

    /** @uses UserController::search */
    Route::middleware(['permission:'.Permission::VIEW_POSITIONS->value])
        ->get('/search', 'search')
        ->name('search');
});

// Fund Sources
Route::prefix('fund-sources')->controller(FundSourceController::class)->name('fund-sources.')->group(function () {
    /** @uses PositionController::fetch */
    Route::middleware(['permission:'.Permission::VIEW_FUNDS->value])
        ->get('', 'fetch')->name('index');

    /** @uses UserController::search */
    Route::middleware(['permission:'.Permission::VIEW_FUNDS->value])
        ->get('/search', 'search')
        ->name('search');
});
