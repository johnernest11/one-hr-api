<?php

use App\Enums\Permission;
use App\Http\Controllers\ItemController;

Route::middleware(['auth:token', 'verified.api'])->controller(ItemController::class)->name('item.')->group(function () {
    /** @uses ItemController::index */
    Route::middleware(['permission:'.Permission::VIEW_ITEMS->value])->get('', 'index')->name('index');

    /** @uses ItemController::search */
    Route::middleware(['permission:'.Permission::VIEW_ITEMS->value])->get('/search', 'search')->name('search');

    /** @uses ItemController::show */
    Route::middleware(['permission:'.Permission::VIEW_ITEMS->value])->get('{item}', 'show')->name('show');

    /** @uses ItemController::store */
    Route::middleware(['permission:'.Permission::CREATE_ITEMS->value])->post('', 'store')->name('store');

    /** @uses ItemController::update */
    Route::middleware(['permission:'.Permission::UPDATE_ITEMS->value])->put('{item}', 'update')->name('update');
});
