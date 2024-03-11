<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\SanctumAuthController;
use App\Http\Controllers\Auth\VerifyController;

Route::controller(SanctumAuthController::class)->group(function () {
    /** @uses SanctumAuthController::store */
    Route::middleware(['throttle:10,1'])->post('tokens', 'store')->name('auth.store');

    /** @uses SanctumAuthController::destroy */
    Route::middleware(['auth:sanctum', 'verified.api'])->delete('tokens', 'destroy')->name('auth.destroy');

    /** @uses SanctumAuthController::fetch */
    Route::middleware(['auth:sanctum', 'verified.api'])->get('tokens', 'fetch')->name('auth.fetch');

    /** @uses SanctumAuthController::revoke */
    Route::middleware(['auth:sanctum', 'verified.api'])->post('tokens/revoke', 'revoke')->name('auth.revoke');

    /** @uses SanctumAuthController::register */
    Route::middleware(['throttle:10,1'])->post('register', 'register')->name('auth.register');
});

Route::controller(VerifyController::class)->group(function () {
    /** @uses VerifyController::resendEmailVerification */
    Route::middleware(['auth:sanctum'])
        ->get('email/send-verification', 'resendEmailVerification')
        ->name('auth.verification.resend');

    /** @uses VerifyController::verifyEmail */
    Route::middleware(['signed:relative'])
        ->get('email/verify/{id}/{hash}', 'verifyEmail')
        ->name('verification.verify');
});

Route::controller(PasswordController::class)->group(function () {
    /** @uses AuthController::forgotPassword */
    Route::post('forgot-password', 'forgotPassword')->name('auth.password.forgot');

    /** @uses AuthController::resetPassword */
    Route::post('reset-password', 'resetPassword')->name('auth.password.reset');
});
