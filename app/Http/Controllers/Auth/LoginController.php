<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Kalau request datang dari domain/subdomain tenant tertentu (lihat
        // ResolveTenantFromDomain), batasi login cuma untuk user tenant itu —
        // supaya user tenant lain (meski tahu password-nya) tidak bisa masuk
        // lewat domain tenant yang salah. Domain utama (MKO) tidak terpengaruh
        // sama sekali karena middleware tidak resolve apa pun di domain itu.
        if (app()->bound('resolvedTenant')) {
            $credentials['tenant_id'] = app('resolvedTenant')->id;
        }

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Email atau password salah.'])
                ->onlyInput('email');
        }

        // Akun hasil registrasi self-serve belum bisa login sampai link
        // verifikasi email di-klik (lihat TenantRegistrationController) —
        // status tenant TRIAL/ACTIVE TIDAK dicek di sini, cuma verifikasi
        // email yang jadi gate akses sungguhan.
        if (! Auth::user()->email_verified_at) {
            Auth::logout();

            return back()
                ->withErrors(['email' => 'Email Anda belum diverifikasi. Cek inbox/spam, atau minta kirim ulang link verifikasi.'])
                ->onlyInput('email');
        }

        // Update last login — WAJIB pakai try-catch agar tidak crash
        try {
            Auth::user()->forceFill([
                'last_login_at' => now()
            ])->saveQuietly();
        } catch (\Exception $e) {
            // Jika kolom belum ada, login tetap lanjut
            \Log::warning('last_login_at update failed: ' . $e->getMessage());
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
