<?php

namespace App\Services;

use App\Models\AppSetting;

class SmtpConfigService
{
    public static function applyFromSettings(?AppSetting $setting = null): void
    {
        try {
            $setting ??= AppSetting::current();
        } catch (\Throwable) {
            return;
        }

        if (! $setting?->smtp_host) {
            return;
        }

        config([
            'mail.default'                 => 'smtp',
            'mail.mailers.smtp.host'       => $setting->smtp_host,
            'mail.mailers.smtp.port'       => (int) ($setting->smtp_port ?? 587),
            'mail.mailers.smtp.username'   => $setting->smtp_username,
            'mail.mailers.smtp.password'   => $setting->smtp_password,
            'mail.mailers.smtp.encryption' => $setting->smtp_encryption ?? 'tls',
            'mail.from.address'            => $setting->smtp_from_address ?: ($setting->contact_email ?: 'noreply@app.com'),
            'mail.from.name'               => $setting->smtp_from_name ?: $setting->app_name,
        ]);
    }
}
