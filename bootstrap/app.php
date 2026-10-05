<?php

use App\Http\Middleware\EnsureRoutePermission;
use App\Http\Middleware\SetLocale;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\NotificationServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

$modulesPath = realpath(__DIR__.'/../Modules');

$defaultUploadLimit = '256M';

$configuredUploadLimit = $_ENV['DB_BACKUP_UPLOAD_MAX_FILESIZE']
    ?? $_SERVER['DB_BACKUP_UPLOAD_MAX_FILESIZE']
    ?? $defaultUploadLimit;

if (! empty($configuredUploadLimit)) {
    @ini_set('upload_max_filesize', (string) $configuredUploadLimit);
}

$configuredPostLimit = $_ENV['DB_BACKUP_POST_MAX_SIZE']
    ?? $_SERVER['DB_BACKUP_POST_MAX_SIZE']
    ?? $configuredUploadLimit;

if (! empty($configuredPostLimit)) {
    @ini_set('post_max_size', (string) $configuredPostLimit);
}

$moduleProviders = [];

if ($modulesPath !== false && is_dir($modulesPath)) {
    foreach (glob($modulesPath.'/*/Providers/*ServiceProvider.php') ?: [] as $providerFile) {
        $relative = substr($providerFile, strlen($modulesPath) + 1);
        $relative = str_replace('\\', '/', $relative);
        $relative = str_replace('.php', '', $relative);

        $moduleProviders[] = 'Modules\\'.str_replace('/', '\\', $relative);
    }

    $moduleProviders = array_values(array_unique($moduleProviders));
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        \App\Console\Commands\CheckTranslations::class,
        \App\Console\Commands\SendZakatDueNotifications::class,
        \App\Console\Commands\SendExpenseDueNotifications::class,
        \App\Console\Commands\SendDebtDueNotifications::class,
    ])
    ->withProviders([
        AppServiceProvider::class,
        AuthServiceProvider::class,
        NotificationServiceProvider::class,
        ...$moduleProviders,
    ])
    ->withMiddleware(function ($middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'permission.route' => EnsureRoutePermission::class,
        ]);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // الثقة في الـ reverse proxy علشان Laravel يولّد روابط https لما الدخول من الدومين
        $middleware->trustProxies(at: '*');

        // ضيف SetLocale في web (append = يشتغل بعد وسطاء الويب الافتراضيين)
        $middleware->web(append: [
            SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (PostTooLargeException $exception, Request $request) {
            if (! $request->routeIs('settings.database.import')) {
                return null;
            }

            $maxKilobytes = (int) config('backup.import.max_upload_kilobytes', 0);
            $maxMegabytes = $maxKilobytes > 0
                ? (int) ceil($maxKilobytes / 1024)
                : null;

            $message = $maxMegabytes
                ? __('setting.Database Import Too Large', ['size' => number_format($maxMegabytes)])
                : __('setting.Database Import Error');

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                ], 413);
            }

            return back()->with('error', $message);
        });
    })
    ->create();
