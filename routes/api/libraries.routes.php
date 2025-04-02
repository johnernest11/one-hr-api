<?php

use App\Enums\Permission;
use App\Http\Controllers\Libraries\PositionController;

// Positions
Route::controller(PositionController::class)->group(function () {
    /** @uses PositionController::fetch */
    Route::middleware(['permission:'.Permission::VIEW_POSITIONS->value])
        ->get('positions', 'fetch')->name('positions.index');

    /** @uses UserController::search */
    Route::middleware(['permission:'.Permission::VIEW_POSITIONS->value])
        ->get('/positions/search', 'search')
        ->name('positions.search');
});
