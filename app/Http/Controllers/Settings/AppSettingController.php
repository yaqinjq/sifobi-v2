<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'app_name' => ['required', 'string', 'max:100'],
            'app_tagline' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico', 'max:512'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
        ]);

        $setting = AppSetting::current();
        $tenantId = (int) $request->user()->tenant_id;

        $data = collect($validated)
            ->only(['app_name', 'app_tagline', 'primary_color', 'contact_email', 'contact_phone'])
            ->all();

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
}
