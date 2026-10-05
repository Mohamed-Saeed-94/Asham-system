<?php

use Illuminate\Support\Facades\Route;
use Modules\Investors\Http\Controllers\AjaxInvestorController;
use Modules\Investors\Http\Controllers\InvestorImportController;
use Modules\Investors\Http\Controllers\InvestorTransactionController;
use Modules\Investors\Http\Controllers\InvestorController;
use Modules\Investors\Http\Controllers\InvestorReportController;
use Modules\Investors\Http\Controllers\InvestorStatementController;
use Modules\Investors\Http\Controllers\InvestorLedgerController;
use Modules\Investors\Http\Controllers\InvestorLedgerImportController;

Route::middleware(['web', 'auth', 'permission.route'])->group(function () {
    Route::prefix('investors/import')->name('investors.')->group(function () {
        Route::get('/',               [InvestorImportController::class, 'create'])->name('import.form');
        Route::post('/',              [InvestorImportController::class, 'store'])->name('import');
        Route::get('/template',       [InvestorImportController::class, 'template'])->name('import.template');
        Route::get('/failures/fix',   [InvestorImportController::class, 'exportFailuresFix'])->name('import.failures.fix');
        Route::post('/pending/{token}/confirm', [InvestorImportController::class, 'confirmPending'])
            ->name('import.pending.confirm');
        Route::post('/pending/{token}/ignore', [InvestorImportController::class, 'ignorePending'])
            ->name('import.pending.ignore');
        Route::post('/pending/{token}/store-new', [InvestorImportController::class, 'storePendingAsNew'])
            ->name('import.pending.store-new');
    });

    Route::get('investors/dashboard', [InvestorController::class, 'dashboard'])
        ->name('investors.dashboard');

    Route::get('investors/export', [InvestorController::class, 'export'])
        ->name('investors.export');

    Route::resource('investors', InvestorController::class);
    Route::resource('investor-transactions', InvestorTransactionController::class);

    Route::prefix('investors/ledger')->name('investors.ledger.')->group(function () {
        Route::get('/shortcuts', [InvestorLedgerController::class, 'shortcuts'])->name('shortcuts');
        Route::get('/shortcuts/capital', [InvestorLedgerController::class, 'capitalShortcut'])->name('shortcuts.capital');
        Route::get('/shortcuts/liquidity-in', [InvestorLedgerController::class, 'liquidityInShortcut'])->name('shortcuts.liquidity_in');
        Route::get('/shortcuts/liquidity-out', [InvestorLedgerController::class, 'liquidityOutShortcut'])->name('shortcuts.liquidity_out');
        Route::get('/shortcuts/zakat', [InvestorLedgerController::class, 'zakatShortcut'])->name('shortcuts.zakat');
        Route::get('/create', [InvestorLedgerController::class, 'create'])->name('create');
        Route::get('/split/create', [InvestorLedgerController::class, 'split'])->name('split.create');
        Route::get('/export', [InvestorLedgerController::class, 'export'])->name('export');
        Route::get('/import', [InvestorLedgerImportController::class, 'create'])->name('import.form');
        Route::post('/import', [InvestorLedgerImportController::class, 'store'])->name('import');
        Route::get('/import/template', [InvestorLedgerImportController::class, 'template'])->name('import.template');
        Route::get('/import/failures/fix', [InvestorLedgerImportController::class, 'exportFailuresFix'])->name('import.failures.fix');
    });

    Route::get('/investors/{investor}/cash', [AjaxInvestorController::class, 'liquidity'])
        ->name('investors.cash');
    Route::get('/investors/{investor}/liquidity', [AjaxInvestorController::class, 'liquidity'])
        ->name('investors.liquidity');
    Route::get('/investors/{investor}/statement', [InvestorReportController::class, 'statement'])
        ->name('investors.statement.statement');
    Route::get('investors/{investor}/withdrawals', [InvestorReportController::class, 'withdrawals'])
        ->name('investors.withdrawals.withdrawals');
    Route::get('investors/{investor}/withdrawals/ledger', [InvestorReportController::class, 'withdrawalsLedger'])
        ->name('investors.withdrawals.ledger');
    Route::get('investors/{investor}/withdrawals/add-contract', [InvestorReportController::class, 'withdrawalsAddContract'])
        ->name('investors.withdrawals.add-contract');
    Route::get('investors/{investor}/deposits', [InvestorReportController::class, 'deposits'])
        ->name('investors.deposits.deposits');
    Route::get('investors/{investor}/deposits/ledger', [InvestorReportController::class, 'depositsLedger'])
        ->name('investors.deposits.ledger');
    Route::get('investors/{investor}/deposits/installments', [InvestorReportController::class, 'depositsInstallments'])
        ->name('investors.deposits.installments');
    Route::get('investors/{investor}/transactions', [InvestorReportController::class, 'transactions'])
        ->name('investors.transactions.transactions');
    Route::get('reports/investors/outstanding', [InvestorReportController::class, 'outstanding'])
        ->name('reports.investors.outstanding');
    Route::get('reports/investors/deposits-withdrawals', [InvestorReportController::class, 'depositsWithdrawalsReport'])
        ->name('reports.investors.deposits-withdrawals');
    Route::get('reports/investors/Allliquidity', [InvestorReportController::class, 'allliquidity'])
        ->name('reports.investors.Allliquidity');

    Route::get('/ajax/investors/{investor}/liquidity', [AjaxInvestorController::class, 'liquidity'])
        ->name('ajax.investors.liquidity');
});
