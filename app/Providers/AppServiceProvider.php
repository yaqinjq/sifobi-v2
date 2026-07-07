<?php

namespace App\Providers;

use App\Models\AppSetting;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('*', function ($view): void {
            static $resolved = false;
            static $setting = null;

            if (! $resolved) {
                try {
                    $setting = AppSetting::current();
                } catch (\Throwable) {
                    $setting = null;
                }

                $resolved = true;
            }

            $view->with('appSetting', $setting);
        });

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
