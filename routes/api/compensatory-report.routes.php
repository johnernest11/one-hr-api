<?php

use App\Http\Controllers\CompensatoryReportController;

Route::middleware(['auth:token', 'verified.api'])->controller(CompensatoryReportController::class)->name('compensatory-report.')->group(function () {

    /** @uses CompensatoryReportController::index */
    Route::get('', 'index')->name('index');

    /** @uses CompensatoryReportController::show */
    Route::get('{compensatoryReport}', 'show')->name('show');

});
