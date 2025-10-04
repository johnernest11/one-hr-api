<?php

use App\Enums\Permission;
use App\Http\Controllers\ComprehensiveRecords\IndividualBasicDetailController;

Route::middleware(['auth:token', 'verified.api'])->controller(IndividualBasicDetailController::class)->name('individual.')->group(function () {
    Route::middleware(['permission:'.Permission::VIEW_EMPLOYEE_PDS->value])
        ->get('/search', 'search')
        ->name('search');

    Route::middleware(['permission:'.Permission::VIEW_EMPLOYEE_PDS->value])
        ->get('', 'viewAllIndividuals')
        ->name('viewAllIndividuals');

    Route::middleware(['permission:'.Permission::VIEW_EMPLOYEE_PDS->value])
        ->get('{individualBasicDetail}', 'viewSpecificIndividual')
        ->name('viewSpecificIndividual');

    Route::middleware(['permission:'.Permission::CREATE_EMPLOYEE_PDS->value])
        ->post('', 'store')
        ->name('store');

    Route::middleware(['permission:'.Permission::UPDATE_EMPLOYEE_PDS->value])
        ->put('{individualBasicDetail}', 'update')
        ->name('update');

    Route::middleware(['permission:'.Permission::IMPORT_EMPLOYEE_PDS->value])
        ->post('import', 'importPreview')
        ->name('import');
});
