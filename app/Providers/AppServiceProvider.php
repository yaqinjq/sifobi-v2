<?php

namespace App\Providers;

use App\Models\AppSetting;
use App\Services\NotificationBadgeService;
use App\Services\SmtpConfigService;
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
                    SmtpConfigService::applyFromSettings($setting);
                } catch (\Throwable) {
                    $setting = null;
                }

                $resolved = true;
            }

            $view->with('appSetting', $setting);
        });

        View::composer('layouts.app', function ($view): void {
            static $badgesResolved = false;
            static $badges = [];

            if (! $badgesResolved) {
                try {
                    $tenantId = auth()->user()?->tenant_id;
                    $badges = $tenantId ? NotificationBadgeService::getBadges((int) $tenantId) : [];
                } catch (\Throwable) {
                    $badges = [];
                }
                $badgesResolved = true;
            }

            $view->with('notifBadges', $badges);
        });

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
