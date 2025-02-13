<?php

use App\Http\Controllers\AccomplishmentReportController;

Route::middleware(['auth:token', 'verified.api'])->controller(AccomplishmentReportController::class)->name('accomplishment-report.')->group(function () {
    /** @uses AccomplishmentReportController::generateAccomplishmentReport */
    Route::get('{accomplishmentReport}/generate', 'generateAccomplishmentReport')->name('generateAR');

    /** @uses AccomplishmentReportController::index */
    Route::get('', 'index')->name('index');

    /** @uses AccomplishmentReportController::show */
    Route::get('{accomplishmentReport}', 'show')->name('show');

    /** @uses AccomplishmentReportController::store */
    Route::post('', 'store')->name('store');

    /** @uses AccomplishmentReportController::update */
    Route::put('{accomplishmentReport}', 'update')->name('update');
});
