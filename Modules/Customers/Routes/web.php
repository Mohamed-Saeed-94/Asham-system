<?php

use Illuminate\Support\Facades\Route;
use Modules\Customers\Http\Controllers\CustomerController;
use Modules\Customers\Http\Controllers\CustomerImportController;
use Modules\Customers\Http\Controllers\CustomerReportController;

Route::middleware(['web', 'auth', 'permission.route'])->group(function () {
    Route::get('customers/dashboard', [CustomerController::class, 'dashboard'])
        ->name('customers.dashboard');

    Route::prefix('customers/import')->name('customers.')->group(function () {
        Route::get('/',               [CustomerImportController::class, 'create'])->name('import.form');
        Route::post('/',              [CustomerImportController::class, 'store'])->name('import');
        Route::get('/template',       [CustomerImportController::class, 'template'])->name('import.template');
        Route::get('/failures/fix',   [CustomerImportController::class, 'exportFailuresFix'])->name('import.failures.fix');
        Route::post('/pending/{token}/confirm', [CustomerImportController::class, 'confirmPending'])
            ->name('import.pending.confirm');
        Route::post('/pending/{token}/ignore', [CustomerImportController::class, 'ignorePending'])
            ->name('import.pending.ignore');
        Route::post('/pending/{token}/store-new', [CustomerImportController::class, 'storePendingAsNew'])
            ->name('import.pending.store-new');
    });

    Route::post('customers/refresh-statuses', [CustomerController::class, 'refreshStatuses'])
        ->name('customers.refresh-statuses');

    Route::get('customers/export', [CustomerController::class, 'export'])
        ->name('customers.export');

    Route::get('customers/{customer}/reports/monthly/print', [CustomerController::class, 'printMonthlyPayments'])
        ->name('customers.reports.monthly.print');

    Route::resource('customers', CustomerController::class);

    Route::get('reports/customers/delinquent', [CustomerReportController::class, 'delinquent'])
        ->name('reports.customers.delinquent');
    Route::get('reports/customers/unpaid', [CustomerReportController::class, 'unpaid'])
        ->name('reports.customers.unpaid');
    Route::get('reports/customers/active', [CustomerReportController::class, 'active'])
        ->name('reports.customers.active');
    Route::get('reports/customers/contracts', [CustomerReportController::class, 'contracts'])
        ->name('reports.customers.contracts');
});
