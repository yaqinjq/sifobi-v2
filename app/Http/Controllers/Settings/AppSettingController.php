<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AppSettingController extends Controller
{
    public function edit(): View
    {
        $setting = AppSetting::current();

        return view('settings.app.edit', compact('setting'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name'         => ['required', 'string', 'max:100'],
            'app_tagline'      => ['nullable', 'string', 'max:255'],
            'logo'             => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
            'favicon'          => ['nullable', 'file', 'mimes:png,ico', 'max:512'],
            'primary_color'    => ['nullable', 'string', 'max:20'],
            'contact_email'    => ['nullable', 'email', 'max:150'],
            'contact_phone'    => ['nullable', 'string', 'max:50'],
            'smtp_host'        => ['nullable', 'string', 'max:255'],
            'smtp_port'        => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username'    => ['nullable', 'string', 'max:255'],
            'smtp_password'    => ['nullable', 'string', 'max:255'],
            'smtp_encryption'  => ['nullable', 'string', 'in:tls,ssl,starttls,'],
            'smtp_from_address'=> ['nullable', 'email', 'max:150'],
            'smtp_from_name'   => ['nullable', 'string', 'max:100'],
        ]);

        $setting = AppSetting::current();
        $tenantId = (int) $request->user()->tenant_id;

        $data = collect($validated)
            ->only([
                'app_name', 'app_tagline', 'primary_color', 'contact_email', 'contact_phone',
                'smtp_host', 'smtp_port', 'smtp_username', 'smtp_encryption',
                'smtp_from_address', 'smtp_from_name',
            ])
            ->all();

        if ($request->filled('smtp_password') && $request->input('smtp_password') !== '••••••••') {
            $data['smtp_password'] = $request->input('smtp_password');
        }

        if ($request->hasFile('logo')) {
            if ($setting->logo_path) {
                Storage::disk('public')->delete($setting->logo_path);
            }
            $data['logo_path'] = $request->file('logo')
                ->store("tenants/{$tenantId}/branding", 'public');
        }

        if ($request->hasFile('favicon')) {
            if ($setting->favicon_path) {
                Storage::disk('public')->delete($setting->favicon_path);
            }
            $data['favicon_path'] = $request->file('favicon')
                ->store("tenants/{$tenantId}/branding", 'public');
        }

        $setting->update($data);

        return back()->with('success', 'Pengaturan aplikasi berhasil disimpan.');
    }

    public function testSmtp(Request $request): JsonResponse
    {
        $request->validate(['test_email' => ['required', 'email']]);

        try {
            $settings = AppSetting::query()
                ->where('tenant_id', (int) $request->user()->tenant_id)
                ->first();

            if (! $settings || ! $settings->smtp_host) {
                return response()->json([
                    'success' => false,
                    'message' => 'SMTP belum dikonfigurasi. Isi dan simpan settings dulu.',
                ], 422);
            }

            config([
                'mail.default'                 => 'smtp',
                'mail.mailers.smtp.transport'  => 'smtp',
                'mail.mailers.smtp.host'       => $settings->smtp_host,
                'mail.mailers.smtp.port'       => (int) ($settings->smtp_port ?? 587),
                'mail.mailers.smtp.username'   => $settings->smtp_username,
                'mail.mailers.smtp.password'   => $settings->smtp_password,
                'mail.mailers.smtp.encryption' => $settings->smtp_encryption,
                'mail.from.address'            => $settings->smtp_from_address ?: $settings->smtp_username,
                'mail.from.name'               => $settings->smtp_from_name ?: config('app.name'),
            ]);

            // Flush cached mailer instance agar config baru dipakai
            app('mail.manager')->purge('smtp');

            $toEmail = $request->input('test_email');

            Mail::mailer('smtp')->raw(
                'Test email dari SIFOBI — konfigurasi SMTP berhasil! Dikirim pada: '
                . now()->setTimezone('Asia/Jakarta')->format('d M Y H:i:s') . ' WIB',
                fn ($m) => $m->to($toEmail)->subject('✅ Test SMTP SIFOBI — Berhasil!')
            );

            return response()->json([
                'success' => true,
                'message' => "Email berhasil dikirim ke {$toEmail}. Cek inbox Anda.",
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
