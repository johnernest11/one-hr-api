<?php

use App\Enums\Permission;
use App\Http\Controllers\ComprehensiveRecords\Pds\PersonalDataSheetController;
use App\Models\ComprehensiveRecords\Employee;

// @todo for updating
//Route::middleware(['auth:token', 'verified.api'])->name('employee.')->group(function(){
//Route::prefix('{employee}/personal-data-sheets/')->group(function () {
//// PERSONAL DATA SHEET
//Route::controller(PersonalDataSheetController::class)->name('personal-data-sheet.')->group(function(){
//Route::middleware(['permission:'.Permission::VIEW_EMPLOYEE_PDS->value])
//->get('', 'viewAllConsolidatedData')
//->name('viewAllConsolidatedData');

//Route::middleware(['permission:'.Permission::CREATE_EMPLOYEE_PDS->value])
//->post('', 'store')
//->name('store');

//Route::middleware(['permission:'.Permission::UPDATE_EMPLOYEE_PDS->value])
//->put('', 'update')
//->name('update');
//});
//});
//});
