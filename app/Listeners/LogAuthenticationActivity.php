<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class LogAuthenticationActivity
{
    public function handleLogin(Login $event): void
    {
        activity('auth')
            ->causedBy($event->user)
            ->withProperties(['guard' => $event->guard, 'ip' => request()->ip()])
            ->event('login')
            ->log('User login: '.$event->user->email);
    }

    public function handleLogout(Logout $event): void
    {
        if (! $event->user) {
            return;
        }

        activity('auth')
            ->causedBy($event->user)
            ->withProperties(['guard' => $event->guard, 'ip' => request()->ip()])
            ->event('logout')
            ->log('User logout: '.$event->user->email);
    }
}
