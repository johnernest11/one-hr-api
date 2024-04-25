<?php

use App\Enums\AuthenticationType;
use App\Http\Controllers\Auth\JwtAuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\SanctumAuthController;
use App\Http\Controllers\Auth\VerifyController;
use App\Http\Requests\AuthRequest;
use App\Services\AppSettingsManager;
use App\Services\Authentication\Interfaces\AuthTokenManager;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use App\Services\MfaPipelineManager;
use App\Services\User\UserAccountManager;
use App\Services\User\UserCredentialManager;

// The Controller (Sanctum or JWT) will depend on the route query parameter `?type=sanctum` or `?type=jwt`
Route::group(['as' => 'auth.'], function () {
    $accountManager = resolve(UserAccountManager::class);
    $credentialManager = resolve(UserCredentialManager::class);
    $sanctumAuthService = resolve(PersistentAuthTokenManager::class);
    $jwtAuthService = resolve(AuthTokenManager::class);
    $appSettingsManager = resolve(AppSettingsManager::class);
    $mfaPipelineManager = resolve(MfaPipelineManager::class);

    // We do a conditional for POST /auth/tokens (login)
    Route::middleware(['throttle:api-login', 'lowercase_query:auth_type'])->name('store')
        ->post('tokens', function (AuthRequest $request) use ($accountManager, $credentialManager, $sanctumAuthService, $jwtAuthService, $appSettingsManager, $mfaPipelineManager) {

            $authType = ! is_null($request->get('auth_type')) ? $request->get('auth_type') : null;

            if (is_null($authType) || $authType === AuthenticationType::SANCTUM->value) {
                /** @uses SanctumAuthController::store */
                return (new SanctumAuthController($accountManager, $credentialManager, $sanctumAuthService, $appSettingsManager, $mfaPipelineManager))->store($request);
            }

            /** @uses JwtAuthController::store */
            return (new JwtAuthController($accountManager, $credentialManager, $jwtAuthService, $appSettingsManager, $mfaPipelineManager))->store($request);
        });

    // We do a conditional for POST /auth/register
    Route::middleware(['throttle:api-register', 'lowercase_query:auth_type'])->name('register')
        ->post('register', function (AuthRequest $request) use ($accountManager, $credentialManager, $sanctumAuthService, $jwtAuthService, $appSettingsManager, $mfaPipelineManager) {

            $authType = ! is_null($request->get('auth_type')) ? $request->get('auth_type') : null;

            if (is_null($authType) || $authType === AuthenticationType::SANCTUM->value) {
                /** @uses SanctumAuthController::register */
                return (new SanctumAuthController($accountManager, $credentialManager, $sanctumAuthService, $appSettingsManager, $mfaPipelineManager))->register($request);
            }

            /** @uses JwtAuthController::register */
            return (new JwtAuthController($accountManager, $credentialManager, $jwtAuthService, $appSettingsManager, $mfaPipelineManager))->register($request);
        });
});

// Only Sanctum Auth can fetch and invalidate tokens since they are persisted in the Database
Route::middleware(['auth:token', 'verified.api'])->controller(SanctumAuthController::class)->name('auth.')->group(function () {
    /** @uses SanctumAuthController::fetch */
    Route::get('tokens', 'fetch')->name('fetch');

    /** @uses SanctumAuthController::invalidateCurrent */
    Route::delete('tokens', 'invalidateCurrent')->name('destroy');

    /** @uses SanctumAuthController::invalidateMultiple */
    Route::post('tokens/invalidate', 'invalidateMultiple')->name('revoke');
});

// Email Verification
Route::controller(VerifyController::class)->group(function () {
    /** @uses VerifyController::resendEmailVerification */
    Route::middleware(['auth:token'])
        ->get('email/send-verification', 'resendEmailVerification')
        ->name('auth.verification.resend');

    /** @uses VerifyController::verifyEmail */
    Route::middleware(['signed:relative'])
        ->get('email/verify/{id}/{hash}', 'verifyEmail')
        ->name('verification.verify');
});

// Password Management
Route::controller(PasswordController::class)->name('auth.password.')->group(function () {
    /** @uses PasswordController::forgotPassword */
    Route::post('forgot-password', 'forgotPassword')->name('forgot');

    /** @uses PasswordController::resetPassword */
    Route::post('reset-password', 'resetPassword')->name('reset');
});
