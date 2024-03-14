<?php

use App\Enums\Permission;
use App\Http\Controllers\ProfileController;

Route::middleware(['auth:multi', 'verified.api'])->controller(ProfileController::class)->name('profile.')->group(function () {
    /** TODO: Create custom permission middleware **/

    /** @uses ProfileController::view */
    Route::middleware([])->get('', 'view')->name('view');

    /** @uses ProfileController::update */
    Route::middleware([])->patch('', 'update')->name('update');

    /** @uses ProfileController::uploadProfilePicture */
    Route::middleware([])->post('profile-picture', 'uploadProfilePicture')->name('upload.profile-picture');

    /** @uses ProfileController::changePassword */
    Route::middleware([])->patch('password', 'changePassword')->name('change.password');
});
