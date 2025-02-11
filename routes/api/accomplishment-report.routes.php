<?php

use App\Http\Controllers\AccomplishmentReportController;

Route::middleware(['auth:token', 'verified.api'])->controller(AccomplishmentReportController::class)->name('accomplishment-report.')->group(function () {
    /** @uses AccomplishmentReportController::generateAccomplishmentReport */
    Route::get('generate/{accomplishmentReport}', 'generateAccomplishmentReport')->name('generateAR');

    /** @uses AccomplishmentReportController::index */
    Route::get('', 'index')->name('index');

    /** @uses AccomplishmentReportController::show */
    Route::get('{accomplishmentReport}', 'show')->name('show');

    /** @uses AccomplishmentReportController::update */
    Route::patch('{accomplishmentReport}', 'update')->name('update');

    /** @uses AccomplishmentReportController::store */
    Route::post('', 'store')->name('store');
});
