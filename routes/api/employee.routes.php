<?php

use App\Enums\Permission;
use App\Http\Controllers\QrCodeController;

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
});

// @todo add routes that do not require employee id here
Route::middleware(['auth:token', 'verified.api'])->group(function () {
    Route::controller(QrCodeController::class)->name('qr-codes.')->group(function () {
        /** @uses QrCodeController::verifyQr */
        Route::middleware(['permission:'.Permission::VERIFY_QR_CODE->value])
            ->post('/verify-qr', 'verifyQr')->name('verify-qr');
    });
});
