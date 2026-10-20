<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\SmtpConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Password::sendResetLink() memicu notifikasi ResetPassword bawaan
        // Laravel, yang TIDAK tahu soal SMTP per-tenant SIFOBI (beda dengan
        // WorkflowNotification yang menerapkannya sendiri). Biasanya SMTP
        // tenant diterapkan lewat View::composer('*') di AppServiceProvider,
        // tapi request ini cuma redirect (tidak pernah merender view), jadi
        // composer itu tidak pernah jalan -- akibatnya email reset terkirim
        // (atau gagal senyap) lewat config mail default, bukan SMTP tenant
        // yang sudah dikonfigurasi. Diterapkan eksplisit di sini, seperti
        // yang dilakukan WorkflowNotification.
        $user = User::withoutGlobalScopes()->where('email', $request->input('email'))->first();
        if ($user) {
            SmtpConfigService::applyFromSettings(AppSetting::forTenant((int) $user->tenant_id));
        }

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', 'Link reset password telah dikirim ke email Anda.')
            : back()->withInput()->withErrors(['email' => 'Email tidak ditemukan atau tidak dapat diproses.']);
    }
}
