<?php

use App\Http\Controllers\AjaxAccountController;
use App\Http\Controllers\AjaxProductTypeController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\LanguageController;
// use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Accounts\GoodsEntriesController;
use App\Http\Controllers\Accounts\GoodsSalesEntriesController;
use App\Http\Controllers\Setting\DatabaseBackupController;
use App\Http\Controllers\Setting\PermissionManagementController;
use App\Http\Controllers\Setting\RoleManagementController;
use App\Http\Controllers\Setting\RolePermissionController;
use App\Http\Controllers\Setting\SettingController;
use App\Http\Controllers\Setting\AccountSettingsController;
use App\Http\Controllers\Setting\SidebarPermissionController;
use App\Http\Controllers\UserRoleController; // ✅ لإدارة أدوار المستخدمين
use App\Http\Controllers\NoteController;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

// لغة الواجهة
Route::post('/lang/toggle', [LanguageController::class, 'toggle'])->name('lang.toggle');
Route::get('/lang/{locale}', [LanguageController::class, 'switch'])->name('lang.switch');

// الصفحة الرئيسية (لو مفيش يوزر يوجّه للتسجيل، غير كده تسجيل الدخول)
Route::get('/', function () {
    return User::count() == 0
        ? redirect()->route('register')
        : redirect()->route('login');
});

// $setting يصل للواجهة عبر View::composer في AppServiceProvider (بدون استعلام أثناء تحميل الـroutes)
Route::view('/loading', 'loading')->name('loading');

Route::middleware(['auth', 'permission.route'])->group(function () {

    Route::get('/global-search', GlobalSearchController::class)
        ->name('global-search');

    Route::get('/home', function () {
        return Schema::hasTable('settings') && Setting::count() > 0
            ? redirect()->route('dashboard')
            : redirect()->route('settings.create');
    })->name('home');

    // الداشبورد (متزوّد عندك بـ can:view-dashboard)
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('can:view-dashboard')
        ->name('dashboard');

    Route::get('/dashboard/daily-ledger/print', [DashboardController::class, 'printDailyLedger'])
        ->middleware('can:view-dashboard')
        ->name('dashboard.daily-ledger.print');

    // الإعدادات
    Route::prefix('settings')->group(function () {
        Route::resource('settings', SettingController::class);

        Route::get('database', [DatabaseBackupController::class, 'index'])
            ->name('settings.database.index');

        Route::get('database/restore', [DatabaseBackupController::class, 'restore'])
            ->middleware('role:admin')
            ->name('settings.database.restore');

        Route::post('database/export', [DatabaseBackupController::class, 'export'])
            ->middleware('role:admin')
            ->name('settings.database.export');
        Route::post('database/import', [DatabaseBackupController::class, 'import'])
            ->middleware('role:admin')
            ->name('settings.database.import');

        Route::get('account', [AccountSettingsController::class, 'edit'])
            ->name('settings.account.edit')
            ->withoutMiddleware('permission.route');

        Route::put('account/profile', [AccountSettingsController::class, 'updateProfile'])
            ->name('settings.account.profile.update')
            ->withoutMiddleware('permission.route');

        Route::put('account/password', [AccountSettingsController::class, 'updatePassword'])
            ->name('settings.account.password.update')
            ->withoutMiddleware('permission.route');
    });

    // مسارات الموديولات (العملاء/الضامنين/المستثمرين/العقود...) تُسجَّل مرة واحدة من ServiceProvider كل موديول

    Route::resource('notes', NoteController::class)->except(['show']);
    Route::patch('notes/{note}/complete', [NoteController::class, 'complete'])->name('notes.complete');
    Route::patch('notes/{note}/reopen', [NoteController::class, 'reopen'])->name('notes.reopen');

    Route::prefix('accounts/entries/goods')->name('accounts.entries.goods.pay.')->group(function () {
        Route::get('/', [GoodsEntriesController::class, 'index'])
            ->name('index')
            ->middleware('permission:accounts.entries.view');

        Route::post('/', [GoodsEntriesController::class, 'store'])
            ->name('store')
            ->middleware('permission:accounts.entries.create');

        Route::post('/partial', [GoodsEntriesController::class, 'storePartial'])
            ->name('store-partial')
            ->middleware('permission:accounts.entries.create');
    });

    Route::prefix('accounts/entries/goods/sales')->name('accounts.entries.goods.sales.')->group(function () {
        Route::get('/', [GoodsSalesEntriesController::class, 'index'])
            ->name('index')
            ->middleware('permission:accounts.entries.view');

        Route::post('/', [GoodsSalesEntriesController::class, 'store'])
            ->name('store')
            ->middleware('permission:accounts.entries.create');

        Route::post('/partial', [GoodsSalesEntriesController::class, 'storePartial'])
            ->name('store-partial')
            ->middleware('permission:accounts.entries.create');
    });
    // AJAX مساعدة
    Route::get('/product-types/{productType}/available', [AjaxProductTypeController::class, 'available'])
        ->name('product-types.available');

    // سجلات التدقيق
    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:view-audit-logs')
        ->name('audit.logs');
    Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])
        ->middleware('permission:view-audit-logs')
        ->name('audit.logs.show');
    Route::delete('/audit-logs/purge', [AuditLogController::class, 'destroyRange'])
        ->middleware('permission:audit.logs.purge')
        ->name('audit.logs.purge');
    Route::post('/audit-logs/{auditLog}/revert', [AuditLogController::class, 'revert'])->name('audit.logs.revert');

    // المتاح في الحسابات (بنكي/خزنة)
    Route::get('/ajax/accounts/availability',      [AjaxAccountController::class, 'availability'])->name('ajax.accounts.availability');
    Route::get('/ajax/accounts/availability-bulk', [AjaxAccountController::class, 'availabilityBulk'])->name('ajax.accounts.availability.bulk');

    // مستثمرين العقد
    // أعذار/تأجيل الأقساط Ajax أصبحت ضمن وحدة العقود

    // // البروفايل
    // Route::get('/profile',   [ProfileController::class, 'edit'])->name('profile.edit');
    // Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    // Route::delete('/profile',[ProfileController::class, 'destroy'])->name('profile.destroy');

    // ✅ إدارة أدوار المستخدمين (محمية بدور admin)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/settings/permissions', [PermissionManagementController::class, 'index'])
            ->name('settings.permissions.index');
        Route::post('/settings/permissions', [PermissionManagementController::class, 'store'])
            ->name('settings.permissions.store');
        Route::delete('/settings/permissions/{permission}', [PermissionManagementController::class, 'destroy'])
            ->name('settings.permissions.destroy');

        Route::get('/settings/roles', [RoleManagementController::class, 'index'])
            ->name('settings.roles.index');
        Route::post('/settings/roles', [RoleManagementController::class, 'store'])
            ->name('settings.roles.store');
        Route::delete('/settings/roles/{role}', [RoleManagementController::class, 'destroy'])
            ->name('settings.roles.destroy');

        Route::get('/settings/roles-permissions', [RolePermissionController::class, 'index'])
            ->name('settings.roles.permissions');
        Route::put('/settings/roles-permissions/{role}', [RolePermissionController::class, 'update'])
            ->name('settings.roles.permissions.update');

        Route::get('/settings/sidebar-permissions', [SidebarPermissionController::class, 'index'])
            ->name('settings.sidebar-permissions.index');
        Route::post('/settings/sidebar-permissions', [SidebarPermissionController::class, 'update'])
            ->name('settings.sidebar-permissions.update');

        Route::get('/users',               [UserRoleController::class, 'index'])->name('users.index');
        Route::get('/users/{user}/roles',  [UserRoleController::class, 'edit'])->name('users.roles.edit');
        Route::put('/users/{user}/roles',  [UserRoleController::class, 'update'])->name('users.roles.update');
    });
});

require __DIR__.'/auth.php';
