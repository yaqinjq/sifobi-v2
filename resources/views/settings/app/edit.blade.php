@extends('layouts.app')

@section('title', 'Pengaturan Aplikasi')

@section('content')
<x-sf.page-header title="Pengaturan Aplikasi" subtitle="Logo, nama, favicon, dan kontak" back="{{ route('settings.index') }}" />

<div class="px-4 py-5 pb-24 lg:px-6 lg:py-6 max-w-4xl mx-auto w-full">
    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700 mb-4">
            {{ $errors->first() }}
        </div>
    @endif

    <x-sf.card title="Identitas Aplikasi">
        <form method="POST"
              action="{{ route('settings.app.update') }}"
              enctype="multipart/form-data"
              class="space-y-5"
              x-data="{
                  logoPreview: @js($setting->logo_path ? \Illuminate\Support\Facades\Storage::url($setting->logo_path) : ''),
                  faviconPreview: @js($setting->favicon_path ? \Illuminate\Support\Facades\Storage::url($setting->favicon_path) : '')
              }">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-sf.form-group label="Nama Aplikasi" for="app_name" :required="true">
                    <input type="text"
                           name="app_name"
                           id="app_name"
                           value="{{ old('app_name', $setting->app_name) }}"
                           class="sf-input text-base"
                           placeholder="SIFOBI"
                           required
                           maxlength="100">
                </x-sf.form-group>

                <x-sf.form-group label="Tagline" for="app_tagline">
                    <input type="text"
                           name="app_tagline"
                           id="app_tagline"
                           value="{{ old('app_tagline', $setting->app_tagline) }}"
                           class="sf-input text-base"
                           placeholder="Food & Beverage Inventory System"
                           maxlength="255">
                </x-sf.form-group>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-gray-50 p-4">
                <p class="sf-label mb-3">Logo Aplikasi</p>
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="w-full sm:w-40 h-24 rounded-2xl border-2 border-dashed border-gray-200 bg-white flex items-center justify-center overflow-hidden">
                        <img x-show="logoPreview" :src="logoPreview" class="max-h-full max-w-full object-contain p-3" alt="Preview logo">
                        <span x-show="!logoPreview" class="text-gray-300 text-xs">Belum ada logo</span>
                    </div>
                    <div class="flex-1">
                        <input type="file"
                               name="logo"
                               accept="image/png,image/jpeg,image/svg+xml"
                               @change="logoPreview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : logoPreview"
                               class="block w-full text-sm text-gray-500 file:mr-4 file:min-h-11 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                        <p class="text-xs text-gray-500 mt-2">
                            PNG, JPG, atau SVG. Maksimal 2MB. Rekomendasi logo transparan rasio 3:1 atau 4:1.
                        </p>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-gray-50 p-4">
                <p class="sf-label mb-3">Favicon</p>
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="w-20 h-20 rounded-2xl border-2 border-dashed border-gray-200 bg-white flex items-center justify-center overflow-hidden">
                        <img x-show="faviconPreview" :src="faviconPreview" class="w-10 h-10 object-contain" alt="Preview favicon">
                        <span x-show="!faviconPreview" class="text-gray-300 text-xs text-center px-2">Belum ada</span>
                    </div>
                    <div class="flex-1">
                        <input type="file"
                               name="favicon"
                               accept="image/png,image/x-icon"
                               @change="faviconPreview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : faviconPreview"
                               class="block w-full text-sm text-gray-500 file:mr-4 file:min-h-11 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                        <p class="text-xs text-gray-500 mt-2">
                            PNG atau ICO. Maksimal 512KB. Ukuran ideal 32x32px atau 64x64px.
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-sf.form-group label="Warna Utama" for="primary_color">
                    <input type="text"
                           name="primary_color"
                           id="primary_color"
                           value="{{ old('primary_color', $setting->primary_color) }}"
                           class="sf-input text-base"
                           placeholder="#1B4332"
                           maxlength="20">
                </x-sf.form-group>

                <x-sf.form-group label="Email Kontak" for="contact_email">
                    <input type="email"
                           name="contact_email"
                           id="contact_email"
                           value="{{ old('contact_email', $setting->contact_email) }}"
                           class="sf-input text-base"
                           placeholder="info@perusahaan.com"
                           maxlength="150">
                </x-sf.form-group>

                <x-sf.form-group label="Telepon Kontak" for="contact_phone">
                    <input type="text"
                           name="contact_phone"
                           id="contact_phone"
                           value="{{ old('contact_phone', $setting->contact_phone) }}"
                           class="sf-input text-base"
                           placeholder="08xxxxxxxxxx"
                           maxlength="50">
                </x-sf.form-group>
            </div>

            {{-- SMTP Setting --}}
            <div class="border-t border-gray-100 pt-5 space-y-4">
                <div>
                    <p class="text-sm font-semibold text-gray-800">Konfigurasi Email (SMTP)</p>
                    <p class="text-xs text-gray-500 mt-0.5">Diperlukan untuk fitur lupa password dan notifikasi email.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-sf.form-group label="SMTP Host" for="smtp_host">
                        <input type="text"
                               name="smtp_host"
                               id="smtp_host"
                               value="{{ old('smtp_host', $setting->smtp_host) }}"
                               class="sf-input text-base"
                               placeholder="smtp.gmail.com">
                    </x-sf.form-group>

                    <x-sf.form-group label="SMTP Port" for="smtp_port">
                        <input type="number"
                               name="smtp_port"
                               id="smtp_port"
                               value="{{ old('smtp_port', $setting->smtp_port ?? 587) }}"
                               class="sf-input text-base"
                               placeholder="587"
                               min="1" max="65535">
                    </x-sf.form-group>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-sf.form-group label="Username SMTP" for="smtp_username">
                        <input type="text"
                               name="smtp_username"
                               id="smtp_username"
                               value="{{ old('smtp_username', $setting->smtp_username) }}"
                               class="sf-input text-base"
                               autocomplete="off"
                               placeholder="user@gmail.com">
                    </x-sf.form-group>

                    <x-sf.form-group label="Password SMTP" for="smtp_password">
                        <input type="password"
                               name="smtp_password"
                               id="smtp_password"
                               value="{{ old('smtp_password', $setting->smtp_password ? '••••••••' : '') }}"
                               class="sf-input text-base"
                               autocomplete="new-password"
                               placeholder="{{ $setting->smtp_password ? '(tidak diubah)' : 'App password' }}">
                    </x-sf.form-group>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <x-sf.form-group label="Enkripsi" for="smtp_encryption">
                        <select name="smtp_encryption" id="smtp_encryption" class="sf-input text-base">
                            <option value="tls" @selected(old('smtp_encryption', $setting->smtp_encryption ?? 'tls') === 'tls')>TLS</option>
                            <option value="ssl" @selected(old('smtp_encryption', $setting->smtp_encryption) === 'ssl')>SSL</option>
                            <option value="" @selected(old('smtp_encryption', $setting->smtp_encryption) === '')>Tidak ada</option>
                        </select>
                    </x-sf.form-group>

                    <x-sf.form-group label="Email Pengirim" for="smtp_from_address">
                        <input type="email"
                               name="smtp_from_address"
                               id="smtp_from_address"
                               value="{{ old('smtp_from_address', $setting->smtp_from_address) }}"
                               class="sf-input text-base"
                               placeholder="noreply@perusahaan.com">
                    </x-sf.form-group>

                    <x-sf.form-group label="Nama Pengirim" for="smtp_from_name">
                        <input type="text"
                               name="smtp_from_name"
                               id="smtp_from_name"
                               value="{{ old('smtp_from_name', $setting->smtp_from_name) }}"
                               class="sf-input text-base"
                               placeholder="SIFOBI System">
                    </x-sf.form-group>
                </div>

                {{-- Tombol tes SMTP --}}
                <div x-data="smtpTester()" class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                    <button type="button"
                            @click="test()"
                            :disabled="loading"
                            class="sf-btn-secondary min-h-10 text-sm flex items-center gap-2">
                        <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span x-text="loading ? 'Mengirim...' : 'Tes Koneksi SMTP'"></span>
                    </button>
                    <p x-show="message"
                       :class="success ? 'text-green-700' : 'text-red-700'"
                       class="text-sm font-medium"
                       x-text="message"></p>
                </div>
            </div>

            <div class="sticky bottom-[calc(5rem+env(safe-area-inset-bottom))] lg:static bg-white/95 backdrop-blur border-t border-gray-100 -mx-4 px-4 py-3 lg:border-0 lg:bg-transparent lg:backdrop-blur-none lg:mx-0 lg:px-0 lg:py-0">
                <button type="submit" class="sf-btn-primary w-full sm:w-auto min-h-11">
                    Simpan Pengaturan
                </button>
            </div>
        </form>
    </x-sf.card>
</div>

@push('scripts')
<script>
function smtpTester() {
    return {
        loading: false,
        success: false,
        message: '',
        async test() {
            this.loading = true;
            this.message = '';
            try {
                const data = new FormData();
                data.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                data.append('smtp_host', document.getElementById('smtp_host').value);
                data.append('smtp_port', document.getElementById('smtp_port').value);
                data.append('smtp_username', document.getElementById('smtp_username').value);
                const pwd = document.getElementById('smtp_password').value;
                if (pwd && pwd !== '••••••••') data.append('smtp_password', pwd);
                data.append('smtp_encryption', document.getElementById('smtp_encryption').value);
                data.append('smtp_from_address', document.getElementById('smtp_from_address').value);
                const res = await fetch('{{ route('settings.app.test-smtp') }}', { method: 'POST', body: data });
                const json = await res.json();
                this.success = json.success;
                this.message = json.message;
            } catch (e) {
                this.success = false;
                this.message = 'Terjadi kesalahan koneksi.';
            } finally {
                this.loading = false;
            }
        }
    };
}
</script>
@endpush
@endsection
