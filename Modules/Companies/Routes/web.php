<?php

use Illuminate\Support\Facades\Route;
use Modules\Companies\Http\Controllers\CompanyController;
use Modules\Companies\Http\Controllers\CompanyTransactionController;

Route::middleware(['web', 'auth', 'permission.route'])->group(function () {
    Route::get('companies/dashboard', [CompanyController::class, 'dashboard'])
        ->name('companies.dashboard');

    Route::prefix('company-transactions')->name('company-transactions.')->group(function () {
        Route::get('expenses', [CompanyTransactionController::class, 'expenses'])->name('expenses.index');
        Route::get('expenses/create', [CompanyTransactionController::class, 'createExpense'])->name('expenses.create');
        Route::post('expenses', [CompanyTransactionController::class, 'storeExpense'])->name('expenses.store');

        Route::get('expenses/payments', [CompanyTransactionController::class, 'expensePayments'])->name('expenses.payments.index');
        Route::get('expenses/payments/create', [CompanyTransactionController::class, 'createExpensePayment'])->name('expenses.payments.create');
        Route::post('expenses/payments', [CompanyTransactionController::class, 'storeExpensePayment'])->name('expenses.payments.store');
    });

    Route::resource('companies', CompanyController::class);
    Route::resource('company-transactions', CompanyTransactionController::class);
});
