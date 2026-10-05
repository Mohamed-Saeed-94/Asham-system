<?php

use Illuminate\Support\Facades\Route;
use Modules\Contracts\Http\Controllers\ContractController;
use Modules\Contracts\Http\Controllers\ContractInstallmentController;
use Modules\Contracts\Http\Controllers\ContractReportController;
use Modules\Contracts\Http\Controllers\ContractsImportController;
use Modules\Contracts\Http\Controllers\ContractsExportController;
use Modules\Contracts\Http\Controllers\ContractClaimController;
use Modules\Contracts\Http\Controllers\ContractNoteController;

Route::middleware(['web', 'auth', 'permission.route'])->group(function () {
    // استيراد العقود
    Route::prefix('contracts/import')->name('contracts.')->group(function () {
        Route::get('/', [ContractsImportController::class, 'create'])->name('import.form');
        Route::post('/', [ContractsImportController::class, 'store'])->name('import');
        Route::get('/basic', [ContractsImportController::class, 'createBasic'])->name('import.basic.form');
        Route::post('/basic', [ContractsImportController::class, 'storeBasic'])->name('import.basic');
        Route::get('/basic/template', [ContractsImportController::class, 'basicTemplate'])->name('import.basic.template');
        Route::get('/investors', [ContractsImportController::class, 'createInvestors'])->name('import.investors.form');
        Route::post('/investors', [ContractsImportController::class, 'storeInvestors'])->name('import.investors');
        Route::get('/investors/template', [ContractsImportController::class, 'investorsTemplate'])->name('import.investors.template');
        Route::get('/investors/failures/fix', [ContractsImportController::class, 'exportInvestorsFailuresFix'])->name('import.investors.failures.fix');
        Route::get('/payments', [ContractsImportController::class, 'createPayments'])->name('import.payments.form');
        Route::post('/payments', [ContractsImportController::class, 'storePayments'])->name('import.payments');
        Route::get('/payments/template', [ContractsImportController::class, 'paymentsTemplate'])->name('import.payments.template');
        Route::get('/payments/failures/fix', [ContractsImportController::class, 'exportPaymentsFailuresFix'])->name('import.payments.failures.fix');
        Route::get('/payments/skipped/export', [ContractsImportController::class, 'exportPaymentsSkipped'])->name('import.payments.skipped.export');

        Route::get('/template', [ContractsImportController::class, 'template'])->name('import.template');
        Route::get('/failures/fix', [ContractsImportController::class, 'exportFailuresFix'])->name('import.failures.fix');
        Route::get('/basic/failures/fix', [ContractsImportController::class, 'exportBasicFailuresFix'])->name('import.basic.failures.fix');
    });

    Route::patch('contract-claims/{contractClaim}/status', [ContractClaimController::class, 'updateStatus'])
        ->name('contract-claims.update-status');
    Route::patch('contract-claims/{contractClaim}/discount', [ContractClaimController::class, 'applyDiscount'])
        ->name('contract-claims.apply-discount');
    Route::patch('contract-claims/{contractClaim}/reopen', [ContractClaimController::class, 'reopen'])
        ->name('contract-claims.reopen');
    Route::post('contract-claims/{contractClaim}/payments', [ContractClaimController::class, 'storePayment'])
        ->name('contract-claims.payments.store');
    Route::resource('contract-claims', ContractClaimController::class)->except(['show', 'create', 'edit']);

    // CRUD العقود
    Route::get('contracts/dashboard', [ContractController::class, 'dashboard'])
        ->name('contracts.dashboard');
    Route::post('contracts/refresh-statuses', [ContractController::class, 'refreshStatuses'])
        ->name('contracts.refresh-statuses');
    Route::put('contracts/{contract}/images', [ContractController::class, 'updateImages'])
        ->name('contracts.images.update');
    Route::prefix('contracts/export')->name('contracts.export.')->group(function () {
        Route::get('/data', [ContractController::class, 'export'])->name('data');
        Route::get('/', [ContractsExportController::class, 'create'])->name('form');
        Route::get('/basic', [ContractsExportController::class, 'basic'])->name('basic');
        Route::get('/investors', [ContractsExportController::class, 'investors'])->name('investors');
        Route::get('/payments', [ContractsExportController::class, 'payments'])->name('payments');
    });

    Route::resource('contracts', ContractController::class);
    Route::post('/contracts/investors/store', [ContractController::class, 'storeInvestors'])->name('contracts.investors.store');

    Route::prefix('contracts/{contract}/notes')->name('contracts.notes.')->group(function () {
        Route::post('/', [ContractNoteController::class, 'store'])->name('store');
        Route::delete('{contract_note}', [ContractNoteController::class, 'destroy'])->name('destroy');
    });

    // الأقساط
    Route::prefix('installments')->name('installments.')->group(function () {
        Route::post('/pay', [ContractInstallmentController::class, 'payInstallment'])->name('pay');
        Route::post('/{installment}/cancel-payment', [ContractInstallmentController::class, 'cancelInstallmentPayment'])
            ->middleware('role:admin')
            ->name('cancel_payment');
        Route::post('/contracts/{contract}/early-settle', [ContractInstallmentController::class, 'earlySettle'])->name('early_settle');
        Route::post('/defer/{id}', [ContractInstallmentController::class, 'deferAjax'])->name('defer');
        Route::post('/excuse/{id}', [ContractInstallmentController::class, 'excuseAjax'])->name('excuse');
    });

    // التقارير والعرض والطباعة
    Route::get('reports/contracts/office-outstanding', [ContractReportController::class, 'officeOutstanding'])->name('reports.contracts.office_outstanding');
    Route::get('reports/contracts/remaining-summary', [ContractReportController::class, 'remainingSummary'])->name('reports.contracts.remaining_summary');
    Route::get('reports/contracts/status/{status}', [ContractReportController::class, 'status'])->name('reports.contracts.status');
    Route::get('reports/contracts/without-investor', [ContractReportController::class, 'withoutInvestor'])->name('reports.contracts.without_investor');
    Route::get('/contracts/{contract}/print', [ContractReportController::class, 'show'])->name('contracts.print');
    Route::get('/contracts/{contract}/closure', [ContractReportController::class, 'closure'])->name('contracts.closure');
    Route::get('/contracts/{contract}/paid', [ContractReportController::class, 'paidInstallments'])->name('contracts.paid');
});
