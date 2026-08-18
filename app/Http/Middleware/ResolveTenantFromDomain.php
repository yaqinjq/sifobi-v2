<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantFromDomain
{
    /**
     * Kalau host request PERSIS domain utama (mis. new-fbi.mykopiogroup.com),
     * middleware ini no-op sepenuhnya — jalur MKO/domain utama tidak berubah
     * sama sekali, tetap 100% pakai resolusi tenant lewat auth()->user()
     * seperti sebelum fitur ini ada.
     *
     * Kalau host cocok custom_domain atau subdomain tenant lain → bind
     * tenant itu ke container sebagai 'resolvedTenant' untuk dipakai
     * AppSetting::current() (branding pra-login) dan LoginController
     * (scoping login ke tenant tsb).
     *
     * Kalau host bukan domain utama TAPI tidak cocok tenant manapun → 404.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $baseDomain = config('app.base_domain');

        if (! $baseDomain || $host === $baseDomain) {
            return $next($request);
        }

        $tenant = Tenant::query()->where('custom_domain', $host)->where('status', 'ACTIVE')->first();

        if (! $tenant && str_ends_with($host, '.'.$baseDomain)) {
            $subdomain = substr($host, 0, -strlen('.'.$baseDomain));
            $tenant = Tenant::query()->where('subdomain', $subdomain)->where('status', 'ACTIVE')->first();
        }

        if (! $tenant) {
            abort(404);
        }

        app()->instance('resolvedTenant', $tenant);

        return $next($request);
    }
}
