<?php

namespace Modules\Guarantors\Providers;

use Illuminate\Support\ServiceProvider;

class GuarantorsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        // Routes/api.php غير محمّل: كان يسجّل نفس مسارات guarantors بدون auth
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'guarantors');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'guarantors');
    }

    public function register(): void
    {
        //
    }
}
