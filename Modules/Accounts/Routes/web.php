<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounts\Http\Controllers\BankAccountController;
use Modules\Accounts\Http\Controllers\SafeController;

Route::middleware(['web', 'auth', 'permission.route'])->prefix('accounts')->name('accounts.')->group(function () {
    Route::resource('bank-accounts', BankAccountController::class)->except(['show']);
    Route::resource('safes', SafeController::class)->except(['show']);
});
