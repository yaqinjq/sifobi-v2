<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * LoginController sudah menolak login baru untuk akun INACTIVE, tapi itu
 * tidak menghentikan sesi yang SUDAH terbuka sebelum admin menonaktifkan
 * akunnya — session Laravel tetap valid sampai kedaluwarsa/logout manual.
 * Middleware ini cek status di setiap request supaya penonaktifan admin
 * langsung berlaku, bukan cuma menghalangi login berikutnya.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && strtoupper((string) $user->status) === 'INACTIVE') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Akun Anda sudah dinonaktifkan. Hubungi admin tenant Anda.',
            ]);
        }

        return $next($request);
    }
}
