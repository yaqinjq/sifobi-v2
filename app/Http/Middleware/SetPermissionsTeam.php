<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class SetPermissionsTeam
{
    /**
     * Set "team" (= tenant) saat ini untuk Spatie Permission sebelum
     * permission/role check apapun jalan di request ini — supaya role
     * seperti "ADMIN" benar-benar terisolasi per tenant (lihat
     * TenantRoleSeeder), bukan dibagikan global lintas tenant seperti
     * sebelum fitur Teams diaktifkan.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
        }

        return $next($request);
    }
}
