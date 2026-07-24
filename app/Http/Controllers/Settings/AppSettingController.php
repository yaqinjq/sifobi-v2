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
        $request->validate([
            'smtp_host'        => ['required', 'string'],
            'smtp_port'        => ['required', 'integer'],
            'smtp_username'    => ['nullable', 'string'],
            'smtp_password'    => ['nullable', 'string'],
            'smtp_encryption'  => ['nullable', 'string'],
            'smtp_from_address'=> ['nullable', 'email'],
        ]);

        config([
            'mail.default'                 => 'smtp',
            'mail.mailers.smtp.host'       => $request->input('smtp_host'),
            'mail.mailers.smtp.port'       => (int) $request->input('smtp_port', 587),
            'mail.mailers.smtp.username'   => $request->input('smtp_username'),
            'mail.mailers.smtp.password'   => $request->input('smtp_password'),
            'mail.mailers.smtp.encryption' => $request->input('smtp_encryption', 'tls'),
            'mail.from.address'            => $request->input('smtp_from_address') ?: $request->user()->email,
            'mail.from.name'               => config('app.name'),
        ]);

        $toEmail = $request->user()->email;

        try {
            Mail::raw('Ini adalah email tes dari SIFOBI. Konfigurasi SMTP berhasil!', function ($message) use ($toEmail): void {
                $message->to($toEmail)->subject('Tes SMTP SIFOBI');
            });

            return response()->json(['success' => true, 'message' => "Email tes berhasil dikirim ke {$toEmail}"]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()], 422);
        }
    }
}
