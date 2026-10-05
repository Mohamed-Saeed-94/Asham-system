<?php

use Illuminate\Support\Facades\Route;
use Modules\Guarantors\Http\Controllers\GuarantorController;
use Modules\Guarantors\Http\Controllers\GuarantorImportController;

Route::middleware(['web', 'auth', 'permission.route'])->group(function () {
    Route::get('guarantors/dashboard', [GuarantorController::class, 'dashboard'])
        ->name('guarantors.dashboard');

    Route::post('guarantors/refresh-statuses', [GuarantorController::class, 'refreshStatuses'])
        ->name('guarantors.refresh-statuses');

    Route::get('guarantors/export', [GuarantorController::class, 'export'])
        ->name('guarantors.export');

    Route::prefix('guarantors/import')->name('guarantors.')->group(function () {
        Route::get('/', [GuarantorImportController::class, 'create'])->name('import.form');
        Route::post('/', [GuarantorImportController::class, 'store'])->name('import');
        Route::get('/template', [GuarantorImportController::class, 'template'])->name('import.template');
        Route::get('/failures/fix', [GuarantorImportController::class, 'exportFailuresFix'])->name('import.failures.fix');
        Route::post('/pending/{token}/confirm', [GuarantorImportController::class, 'confirmPending'])
            ->name('import.pending.confirm');
        Route::post('/pending/{token}/ignore', [GuarantorImportController::class, 'ignorePending'])
            ->name('import.pending.ignore');
        Route::post('/pending/{token}/store-new', [GuarantorImportController::class, 'storePendingAsNew'])
            ->name('import.pending.store-new');
    });

    Route::resource('guarantors', GuarantorController::class);
});
