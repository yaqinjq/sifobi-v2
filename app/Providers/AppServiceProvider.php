<?php

namespace App\Providers;

use App\Listeners\LogAuthenticationActivity;
use App\Models\AppSetting;
use App\Services\Contracts\OrderSuggestionEngine;
use App\Services\NotificationBadgeService;
use App\Services\SmartOrderService;
use App\Services\SmtpConfigService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
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
        $this->app->bind(OrderSuggestionEngine::class, SmartOrderService::class);
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

        View::composer('layouts.app', function ($view): void {
            static $resolved = false;
            static $unreadCount = 0;
            static $recentNotifs = null;

            if (! $resolved) {
                try {
                    $user = auth()->user();
                    $unreadCount = $user?->unreadNotifications()->count() ?? 0;
                    $recentNotifs = $user?->notifications()->latest()->limit(5)->get();
                } catch (\Throwable) {
                    $unreadCount = 0;
                    $recentNotifs = collect();
                }
                $resolved = true;
            }

            $view->with('unreadNotifCount', $unreadCount);
            $view->with('recentNotifs', $recentNotifs ?? collect());
        });

        Event::listen(Login::class, [LogAuthenticationActivity::class, 'handleLogin']);
        Event::listen(Logout::class, [LogAuthenticationActivity::class, 'handleLogout']);

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
