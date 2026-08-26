<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\TenantRegistrationVerifyMail;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;

class TenantRegistrationController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:150'],
            'admin_name' => ['required', 'string', 'max:150'],
            'admin_email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', 'min:8'],
            'business_phone' => ['required', 'string', 'max:30'],
            'business_address' => ['required', 'string', 'max:500'],
            'plan' => ['required', Rule::in(['STARTER', 'GROWTH', 'ENTERPRISE'])],
        ]);

        $code = $this->generateUniqueCode($validated['business_name']);

        $user = DB::transaction(function () use ($validated, $code): User {
            $tenant = Tenant::create([
                'code' => $code,
                'name' => $validated['business_name'],
                'status' => 'TRIAL',
                'trial_ends_at' => now()->addDays(14),
                'settings' => [
                    'business_phone' => $validated['business_phone'],
                    'business_address' => $validated['business_address'],
                    'plan' => $validated['plan'],
                ],
            ]);

            app(TenantRoleSeeder::class)->seedForTenant($tenant->id);

            $user = User::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => $validated['password'],
                'status' => 'ACTIVE',
            ]);

            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
            $user->assignRole('ADMIN');

            return $user;
        });

        Mail::to($user->email)->send(new TenantRegistrationVerifyMail($user));

        return redirect()
            ->route('register')
            ->with('registered_email', $user->email);
    }

    public function verify(User $user): RedirectResponse
    {
        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Email terverifikasi! Selamat datang — Anda dalam masa trial 14 hari.');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::withoutGlobalScopes()
            ->where('email', $validated['email'])
            ->whereNull('email_verified_at')
            ->first();

        if ($user) {
            Mail::to($user->email)->send(new TenantRegistrationVerifyMail($user));
        }

        return back()->with('success', 'Kalau email tersebut terdaftar dan belum diverifikasi, link baru sudah dikirim.');
    }

    private function generateUniqueCode(string $businessName): string
    {
        $base = Str::upper(Str::slug($businessName, '_'));
        $base = substr($base, 0, 26) ?: 'TENANT';

        $code = $base;
        while (Tenant::where('code', $code)->exists()) {
            $code = substr($base, 0, 20).'_'.random_int(1000, 9999);
        }

        return $code;
    }
}
