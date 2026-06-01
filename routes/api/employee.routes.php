<?php

use App\Enums\Permission;
use App\Http\Controllers\DailyTimeRecords\DailyTimeRecordController;
use App\Http\Controllers\DailyTimeRecords\QrCodeController;
use App\Http\Controllers\DailyTimeRecords\TimeLogController;
use App\Http\Controllers\LocatorSlipController;

// Routes that requires employee id
Route::middleware(['auth:token', 'verified.api'])->prefix('/{employee}')->group(function () {
    Route::prefix('qr-codes')->controller(QrCodeController::class)->name('qr-codes.')->group(function () {
        /** @uses QrCodeController::store */
        Route::middleware(['permission:'.Permission::GENERATE_READ_UPDATE_QR_CODE->value])
            ->post('', 'store')->name('store');

        /** @uses QrCodeController::read */
        Route::middleware(['permission:'.Permission::GENERATE_READ_UPDATE_QR_CODE->value])
            ->get('', 'read')->name('read');

        /** @uses QrCodeController::update */
        Route::middleware(['permission:'.Permission::GENERATE_READ_UPDATE_QR_CODE->value])
            ->patch('', 'update')->name('update');
    });

    Route::prefix('daily-time-records')->controller(DailyTimeRecordController::class)->name('daily-time-records.')->group(function () {
        /** @uses DailyTimeRecordController::update */
        Route::middleware(['permission:'.Permission::UPDATE_DTR->value])
            ->put('', 'update')->name('update');

        /** @uses DailyTimeRecordController::generateDtrPerMonth */
        Route::middleware(['permission:'.Permission::VIEW_DTR->value])
            ->get('/generate-dtr', 'generateDailyTimeRecord')->name('generate-dtr');

        /** @uses DailyTimeRecordController::viewDtrPerMonth */
        Route::middleware(['permission:'.Permission::VIEW_DTR->value])
            ->get('/view-dtr', 'viewDtrPerPeriodRange')->name('view-dtr');

        /** @uses DailyTimeRecordController::getLastTimeLog */
        Route::middleware(['permission:'.Permission::VIEW_DTR->value])
            ->get('/last-time-log', 'getLastTimeLog')->name('last-time-log');

        /** @uses DailyTimeRecordController::checkLate */
        Route::middleware(['permission:'.Permission::VIEW_DTR->value])
            ->get('/check-late', 'checkLate')->name('check-late');

    });

    Route::prefix('locator-slips')->controller(LocatorSlipController::class)->name('locator-slips.')->group(function () {
        /** @uses LocatorSlip::create */
        Route::middleware(['permission:'.Permission::CRUD_LOCATOR_SLIP->value])
            ->post('', 'store')->name('store');

        /** @uses LocatorSlip::search */
        Route::middleware(['permission:'.Permission::CRUD_LOCATOR_SLIP->value])
            ->get('/search', 'search')->name('search');

        /** @uses LocatorSlip::checkActiveLog */
        Route::middleware(['permission:'.Permission::CRUD_LOCATOR_SLIP->value])
            ->get('/active', 'checkActiveLog')->name('check-active');

        /** @uses LocatorSlip::viewEmployeeLocator */
        Route::middleware(['permission:'.Permission::CRUD_LOCATOR_SLIP->value])
            ->get('', 'viewEmployeeLocator')->name('view-employee-locator');

        /** @uses LocatorSlip::generate */
        Route::middleware(['permission:'.Permission::CRUD_LOCATOR_SLIP->value])
            ->get('{locatorSlip}/generate', 'generateLocator')->name('generate');

        /** @uses LocatorSlip::show */
        Route::middleware(['permission:'.Permission::CRUD_LOCATOR_SLIP->value])
            ->get('/{locatorSlip}', 'show')->name('view-locator');
    });
});

// @todo add routes that do not require employee id here
Route::middleware(['auth:token', 'verified.api'])->group(function () {
    Route::controller(QrCodeController::class)->name('qr-codes.')->group(function () {
        /** @uses QrCodeController::verifyQr */
        Route::middleware(['permission:'.Permission::VERIFY_QR_CODE->value])
            ->post('/verify-qr', 'verifyQr')->name('verify-qr');
    });

    Route::controller(TimeLogController::class)->name('time-logs.')->middleware(['throttle:api-time-logs'])->group(function () {
        /** @uses TimeLogController::logTime */
        Route::middleware(['permission:'.Permission::LOG_TIME->value])
            ->post('/log-time', 'logTime')->name('log-time');

    });

    Route::prefix('daily-time-records')->controller(DailyTimeRecordController::class)->name('daily-time-records.')->group(function () {
        /** @uses DailyTimeRecordController::index */
        Route::middleware(['permission:'.Permission::VIEW_ALL_TIME_LOGS->value])
            ->get('/time-logs', 'index')->name('index');

        /** @uses DailyTimeRecordController::countWarmBodies */
        Route::middleware(['permission:'.Permission::VIEW_ALL_TIME_LOGS->value])
            ->get('/warm-bodies/count', 'countWarmBodies')->name('count-warm-bodies');

        /** @uses DailyTimeRecordController::countWarmBodiesPerStation */
        Route::middleware(['permission:'.Permission::VIEW_ALL_TIME_LOGS->value])
            ->get('/warm-bodies/station', 'countWarmBodiesPerStation')->name('count-warm-bodies-station');

        /** @uses DailyTimeRecordController::viewWarmBodiesToday */
        Route::middleware(['permission:'.Permission::VIEW_WARM_BODIES_TODAY->value])
            ->get('/warm-bodies/today', 'viewWarmBodiesToday')->name('view-warm-bodies-today');

        /** @uses DailyTimeRecordController::search */
        Route::middleware(['permission:'.Permission::SEARCH_TIME_LOGS->value])
            ->get('/time-logs/search', 'search')->name('search-time-logs');
    });

    Route::prefix('locator-slips')->controller(LocatorSlipController::class)->name('locator-slips.')->group(function () {
        /** @uses LocatorSlip::update */
        Route::middleware(['permission:'.Permission::CRUD_LOCATOR_SLIP->value])
            ->put('/{locatorSlip}', 'update')->name('update');

        /** @uses LocatorSlip::search */
        Route::middleware(['permission:'.Permission::CRUD_LOCATOR_SLIP->value])
            ->get('/search-all', 'searchAll')->name('search-all');

        /** @uses LocatorSlip::readGrouped */
        Route::middleware(['permission:'.Permission::CRUD_LOCATOR_SLIP->value])
            ->get('grouped', 'readGrouped')->name('read-grouped');

        /** @uses LocatorSlip::show */
        Route::middleware(['permission:'.Permission::CRUD_LOCATOR_SLIP->value])
            ->get('/{locatorSlip}', 'show')->name('view-locator');
    });

});
