<?php

use App\Enums\AuthenticationType;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\JwtAuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\SanctumAuthController;
use App\Http\Controllers\Auth\VerifyController;
use App\Http\Requests\AuthRequest;
use App\Interfaces\Services\Authentication\AuthTokenManager;
use App\Interfaces\Services\Authentication\PersistentAuthTokenManager;
use App\Interfaces\Services\UserServiceInterface;

// The Controller (Sanctum or JWT) will depend on the route query parameter `?type=sanctum` or `?type=jwt`
Route::group(['as' => 'auth.'], function () {
    $userService = resolve(UserServiceInterface::class);
    $sanctumAuthService = resolve(PersistentAuthTokenManager::class);
    $jwtAuthService = resolve(AuthTokenManager::class);

    // We do a conditional for POST /auth/tokens (login)
    Route::middleware(['throttle:10,1'])->name('store')->post('tokens', function (AuthRequest $request) use ($userService, $sanctumAuthService, $jwtAuthService) {
        if (! $request->get('type') || $request->get('type') === AuthenticationType::SANCTUM->value) {
            /** @uses SanctumAuthController::store */
            return (new SanctumAuthController($userService, $sanctumAuthService))->store($request);
        }

        /** @uses JwtAuthController::store */
        return (new JwtAuthController($userService, $jwtAuthService))->store($request);
    });

    // We do a conditional for POST /auth/register
    Route::middleware(['throttle:10,1'])->name('register')->post('register', function (AuthRequest $request) use ($userService, $sanctumAuthService, $jwtAuthService) {
        if (! $request->get('type') || $request->get('type') === AuthenticationType::SANCTUM->value) {
            /** @uses SanctumAuthController::register */
            return (new SanctumAuthController($userService, $sanctumAuthService))->register($request);
        }

        /** @uses JwtAuthController::register */
        return (new JwtAuthController($userService, $jwtAuthService))->register($request);
    });
});

// Only Sanctum Auth can fetch and invalidate tokens since they are persisted in the Database
Route::controller(SanctumAuthController::class)->name('auth.')->group(function () {
    /** @uses SanctumAuthController::destroy */
    Route::middleware(['auth:sanctum', 'verified.api'])->delete('tokens', 'destroy')->name('destroy');

    /** @uses SanctumAuthController::fetch */
    Route::middleware(['auth:sanctum', 'verified.api'])->get('tokens', 'fetch')->name('fetch');

    /** @uses SanctumAuthController::revoke */
    Route::middleware(['auth:sanctum', 'verified.api'])->post('tokens/revoke', 'revoke')->name('revoke');
});

// Email Verification
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

// Password Management
Route::controller(PasswordController::class)->name('auth.password.')->group(function () {
    /** @uses AuthController::forgotPassword */
    Route::post('forgot-password', 'forgotPassword')->name('forgot');

    /** @uses AuthController::resetPassword */
    Route::post('reset-password', 'resetPassword')->name('reset');
});
