<?php

use App\Http\Controllers\Libraries\PositionController;

// Positions
Route::controller(PositionController::class)->group(function () {
    /** @uses PositionController::fetch */
    Route::get('positions', 'fetch')->name('positions.index');
});
