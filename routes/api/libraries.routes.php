<?php

use App\Enums\Permission;
use App\Http\Controllers\Libraries\CountryController;
use App\Http\Controllers\Libraries\DivisionController;
use App\Http\Controllers\Libraries\FundSourceController;
use App\Http\Controllers\Libraries\OfficeController;
use App\Http\Controllers\Libraries\PositionController;
use App\Http\Controllers\Libraries\ProgramController;
use App\Http\Controllers\Libraries\SalaryGradeController;
use App\Http\Controllers\Libraries\SectionOrUnitController;

// Positions
Route::prefix('positions')->controller(PositionController::class)->name('positions.')->group(function () {
    /** @uses PositionController::fetch */
    Route::middleware(['permission:'.Permission::VIEW_POSITIONS->value])
        ->get('', 'fetch')->name('index');

    /** @uses PositionController::search */
    Route::middleware(['permission:'.Permission::VIEW_POSITIONS->value])
        ->get('/search', 'search')
        ->name('search');
});

// Fund Sources
Route::prefix('fund-sources')->controller(FundSourceController::class)->name('fund-sources.')->group(function () {
    /** @uses FundSourceController::fetch */
    Route::middleware(['permission:'.Permission::VIEW_FUNDS->value])
        ->get('', 'fetch')->name('index');

    /** @uses FundSourceController::search */
    Route::middleware(['permission:'.Permission::VIEW_FUNDS->value])
        ->get('/search', 'search')
        ->name('search');
});

// Offices
Route::prefix('offices')->controller(OfficeController::class)->name('offices.')->group(function () {
    /** @uses OfficeController::fetch */
    Route::middleware(['permission:'.Permission::VIEW_OFFICES->value])
        ->get('', 'fetch')->name('index');

    /** @uses OfficeController::search */
    Route::middleware(['permission:'.Permission::VIEW_OFFICES->value])
        ->get('/search', 'search')
        ->name('search');
});

// Divisions
Route::prefix('divisions')->controller(DivisionController::class)->name('divisions.')->group(function () {
    /** @uses DivisionController::fetch */
    Route::middleware(['permission:'.Permission::VIEW_DIVISIONS->value])
        ->get('', 'fetch')->name('index');

    /** @uses DivisionController::search */
    Route::middleware(['permission:'.Permission::VIEW_DIVISIONS->value])
        ->get('/search', 'search')
        ->name('search');
});

// Sections or Units
Route::prefix('section-or-units')->controller(SectionOrUnitController::class)->name('section-or-units.')->group(function () {
    /** @uses SectionOrUnitController::fetch */
    Route::middleware(['permission:'.Permission::VIEW_SECTION_OR_UNITS->value])
        ->get('', 'fetch')->name('index');

    /** @uses SectionOrUnitController::search */
    Route::middleware(['permission:'.Permission::VIEW_SECTION_OR_UNITS->value])
        ->get('/search', 'search')
        ->name('search');
});

// Countries
Route::prefix('countries')->controller(CountryController::class)->name('countries.')->group(function () {
    /** @uses CountryController::fetch */
    Route::middleware(['permission:'.Permission::VIEW_COUNTRIES->value])
        ->get('', 'fetch')->name('index');

    /** @uses CountryController::search */
    Route::middleware(['permission:'.Permission::VIEW_COUNTRIES->value])
        ->get('/search', 'search')
        ->name('search');

});

// Salary Grades
Route::prefix('salary-grades')->controller(SalaryGradeController::class)->name('salary-grades.')->group(function () {
    /** @uses SalaryGradeController::fetch */
    Route::middleware(['permission:'.Permission::VIEW_SALARY_GRADES->value])
        ->get('', 'fetch')->name('index');

    /** @uses SalaryGradeController::search */
    Route::middleware(['permission:'.Permission::VIEW_SALARY_GRADES->value])
        ->get('/search', 'search')
        ->name('search');

});

// Programs
Route::prefix('programs')->controller(ProgramController::class)->name('programs.')->group(function () {
    /** @uses ProgramController::fetch */
    Route::middleware(['permission:'.Permission::VIEW_PROGRAMS->value])
        ->get('', 'fetch')->name('index');

    /** @uses ProgramController::search */
    Route::middleware(['permission:'.Permission::VIEW_PROGRAMS->value])
        ->get('/search', 'search')
        ->name('search');

});
