@extends('layouts.app')

@section('title', 'Pengaturan Integrasi')

@section('content')
<x-sf.page-header title="Pengaturan Integrasi" subtitle="Profil API eksternal dan dokumentasi endpoint" back="{{ route('settings.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full space-y-5">
    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <x-sf.card title="Tambah Profil Integrasi">
        <form method="POST"
              action="{{ route('settings.integrations.store') }}"
              class="grid grid-cols-1 lg:grid-cols-2 gap-4"
              x-data="{ authMode: @js(old('auth_mode', 'BEARER')) }">
            @csrf

            <x-sf.form-group label="Kode Unik" for="code" :required="true">
                <input id="code" name="code" value="{{ old('code', 'OCIA') }}" class="sf-input text-base uppercase" required maxlength="50">
            </x-sf.form-group>

            <x-sf.form-group label="Nama Tampilan" for="name" :required="true">
                <input id="name" name="name" value="{{ old('name', 'OCIA - Roastery Kopi') }}" class="sf-input text-base" required maxlength="100">
            </x-sf.form-group>

            <x-sf.form-group label="Base URL" for="base_url" :required="true">
                <input id="base_url" name="base_url" value="{{ old('base_url', 'https://ocia.mykopiogroup.com') }}" class="sf-input text-base" required placeholder="https://ocia.mykopiogroup.com">
            </x-sf.form-group>

            <x-sf.form-group label="Auth Mode" for="auth_mode" :required="true">
                <select id="auth_mode" name="auth_mode" x-model="authMode" class="sf-input text-base" required>
                    @foreach($authTypes as $authType)
                        <option value="{{ $authType }}">{{ $authType }}</option>
                    @endforeach
                </select>
            </x-sf.form-group>

            <div x-show="authMode === 'BEARER'" x-cloak>
                <x-sf.form-group label="Bearer Token" for="auth_token">
                    <input id="auth_token" name="auth_token" value="{{ old('auth_token') }}" class="sf-input text-base" maxlength="255" autocomplete="off">
                </x-sf.form-group>
            </div>

            <div x-show="authMode === 'BASIC'" x-cloak>
                <x-sf.form-group label="Username" for="auth_username">
                    <input id="auth_username" name="auth_username" value="{{ old('auth_username') }}" class="sf-input text-base" maxlength="255" autocomplete="off">
                </x-sf.form-group>
            </div>

            <div x-show="authMode === 'BASIC'" x-cloak>
                <x-sf.form-group label="Password" for="auth_password">
                    <input id="auth_password" name="auth_password" type="password" class="sf-input text-base" maxlength="255" autocomplete="new-password">
                </x-sf.form-group>
            </div>

            <x-sf.form-group label="Endpoint List Outlet" for="meta_outlet_list_path">
                <input id="meta_outlet_list_path" name="meta[outlet_list_path]" value="{{ old('meta.outlet_list_path', '/api/outlets') }}" class="sf-input text-base" maxlength="255">
            </x-sf.form-group>

            <x-sf.form-group label="Endpoint List PO" for="meta_po_list_path">
                <input id="meta_po_list_path" name="meta[po_list_path]" value="{{ old('meta.po_list_path', '/api/available-orders') }}" class="sf-input text-base" maxlength="255">
            </x-sf.form-group>

            <x-sf.form-group label="Endpoint Kirim Order" for="meta_order_path">
                <input id="meta_order_path" name="meta[order_path]" value="{{ old('meta.order_path', '/api/outlet-order') }}" class="sf-input text-base" maxlength="255">
            </x-sf.form-group>

            <x-sf.form-group label="Timeout Detik" for="meta_timeout_seconds">
                <input id="meta_timeout_seconds" name="meta[timeout_seconds]" type="number" min="1" max="120" value="{{ old('meta.timeout_seconds', 10) }}" class="sf-input text-base">
            </x-sf.form-group>

            <input type="hidden" name="health_path" value="/api/health">

            <label class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-gray-700">
                <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-primary-700 focus:ring-primary-500">
                Aktif
            </label>

            <div class="lg:col-span-2 flex justify-end">
                <button type="submit" class="sf-btn-primary min-h-11">Simpan Profil</button>
            </div>
        </form>
    </x-sf.card>

    <div class="space-y-4">
        @forelse($profiles as $profile)
            @php
                $profileCode = $profile->code ?: $profile->provider;
                $profileAuthMode = $profile->auth_mode ?: $profile->auth_type ?: 'NONE';
                $outletPath = data_get($profile->meta, 'outlet_list_path') ?: $profile->outlet_sync_path ?: '/api/outlets';
                $poPath = data_get($profile->meta, 'po_list_path', '/api/available-orders');
                $orderPath = data_get($profile->meta, 'order_path', '/api/outlet-order');
                $timeout = data_get($profile->meta, 'timeout_seconds', 10);
                $endpointRows = [
                    ['function' => 'Sync Outlet', 'method' => 'GET', 'path' => $outletPath],
                    ['function' => 'Ambil List PO', 'method' => 'GET', 'path' => $poPath],
                    ['function' => 'Kirim Order Kopi', 'method' => 'POST', 'path' => $orderPath],
                ];
            @endphp

            <x-sf.card title="{{ $profileCode }} - {{ $profile->name }}">
                <div x-data="integrationActions()" class="space-y-4">
                    <form method="POST"
                          action="{{ route('settings.integrations.update', $profile) }}"
                          class="grid grid-cols-1 lg:grid-cols-2 gap-4"
                          x-data="{ authMode: @js($profileAuthMode) }">
                        @csrf
                        @method('PUT')

                        <x-sf.form-group label="Kode Unik" for="code_{{ $profile->id }}" :required="true">
                            <input id="code_{{ $profile->id }}" name="code" value="{{ old('code', $profileCode) }}" class="sf-input text-base uppercase" required maxlength="50">
                        </x-sf.form-group>

                        <x-sf.form-group label="Nama Tampilan" for="name_{{ $profile->id }}" :required="true">
                            <input id="name_{{ $profile->id }}" name="name" value="{{ old('name', $profile->name) }}" class="sf-input text-base" required maxlength="100">
                        </x-sf.form-group>

                        <x-sf.form-group label="Base URL" for="base_url_{{ $profile->id }}" :required="true">
                            <input id="base_url_{{ $profile->id }}" name="base_url" value="{{ old('base_url', $profile->base_url) }}" class="sf-input text-base" required>
                        </x-sf.form-group>

                        <x-sf.form-group label="Auth Mode" for="auth_mode_{{ $profile->id }}" :required="true">
                            <select id="auth_mode_{{ $profile->id }}" name="auth_mode" x-model="authMode" class="sf-input text-base" required>
                                @foreach($authTypes as $authType)
                                    <option value="{{ $authType }}">{{ $authType }}</option>
                                @endforeach
                            </select>
                        </x-sf.form-group>

                        <div x-show="authMode === 'BEARER'" x-cloak>
                            <x-sf.form-group label="Bearer Token" for="auth_token_{{ $profile->id }}">
                                <input id="auth_token_{{ $profile->id }}" name="auth_token" value="{{ old('auth_token', $profile->auth_token ?: $profile->api_token) }}" class="sf-input text-base" maxlength="255" autocomplete="off">
                            </x-sf.form-group>
                        </div>

                        <div x-show="authMode === 'BASIC'" x-cloak>
                            <x-sf.form-group label="Username" for="auth_username_{{ $profile->id }}">
                                <input id="auth_username_{{ $profile->id }}" name="auth_username" value="{{ old('auth_username', $profile->auth_username ?: $profile->username) }}" class="sf-input text-base" maxlength="255" autocomplete="off">
                            </x-sf.form-group>
                        </div>

                        <div x-show="authMode === 'BASIC'" x-cloak>
                            <x-sf.form-group label="Password Baru" for="auth_password_{{ $profile->id }}" hint="Kosongkan jika tidak ingin mengubah.">
                                <input id="auth_password_{{ $profile->id }}" name="auth_password" type="password" class="sf-input text-base" maxlength="255" autocomplete="new-password">
                            </x-sf.form-group>
                        </div>

                        <x-sf.form-group label="Endpoint List Outlet" for="outlet_list_path_{{ $profile->id }}">
                            <input id="outlet_list_path_{{ $profile->id }}" name="meta[outlet_list_path]" value="{{ old('meta.outlet_list_path', $outletPath) }}" class="sf-input text-base" maxlength="255">
                        </x-sf.form-group>

                        <x-sf.form-group label="Endpoint List PO" for="po_list_path_{{ $profile->id }}">
                            <input id="po_list_path_{{ $profile->id }}" name="meta[po_list_path]" value="{{ old('meta.po_list_path', $poPath) }}" class="sf-input text-base" maxlength="255">
                        </x-sf.form-group>

                        <x-sf.form-group label="Endpoint Kirim Order" for="order_path_{{ $profile->id }}">
                            <input id="order_path_{{ $profile->id }}" name="meta[order_path]" value="{{ old('meta.order_path', $orderPath) }}" class="sf-input text-base" maxlength="255">
                        </x-sf.form-group>

                        <x-sf.form-group label="Timeout Detik" for="timeout_seconds_{{ $profile->id }}">
                            <input id="timeout_seconds_{{ $profile->id }}" name="meta[timeout_seconds]" type="number" min="1" max="120" value="{{ old('meta.timeout_seconds', $timeout) }}" class="sf-input text-base">
                        </x-sf.form-group>

                        <input type="hidden" name="health_path" value="{{ data_get($profile->meta, 'health_path', '/api/health') }}">

                        <label class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-gray-700">
                            <input type="checkbox" name="is_active" value="1" @checked($profile->is_active) class="rounded border-gray-300 text-primary-700 focus:ring-primary-500">
                            Aktif
                        </label>

                        <div class="lg:col-span-2 flex flex-col gap-3">
                            <div class="rounded-2xl border border-gray-100 overflow-hidden">
                                <div class="grid grid-cols-1 md:grid-cols-3 bg-gray-50 text-xs font-semibold text-gray-600">
                                    <div class="px-4 py-3">Fungsi</div>
                                    <div class="px-4 py-3">Endpoint</div>
                                    <div class="px-4 py-3">Status</div>
                                </div>
                                @foreach($endpointRows as $endpoint)
                                    <div class="grid grid-cols-1 md:grid-cols-3 border-t border-gray-100 text-sm">
                                        <div class="px-4 py-3 font-medium text-gray-900">{{ $endpoint['function'] }}</div>
                                        <div class="px-4 py-3 text-gray-600 break-all">{{ $endpoint['method'] }} {{ rtrim($profile->base_url, '/') }}{{ $endpoint['path'] }}</div>
                                        <div class="px-4 py-3">
                                            <span class="badge-draft">Belum dites</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <p class="text-sm text-gray-500">
                                    Sync terakhir: {{ $profile->last_synced_at?->format('d M Y H:i') ?? 'belum pernah' }}
                                </p>
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <button type="button" class="sf-btn-secondary min-h-11" :disabled="loading" @click="post(@js(route('settings.integrations.test', $profile)))">
                                        Test Koneksi
                                    </button>
                                    <button type="button" class="sf-btn-secondary min-h-11" :disabled="loading" @click="post(@js(route('settings.integrations.sync-outlets', $profile)))">
                                        Sync Outlet
                                    </button>
                                    <button type="submit" class="sf-btn-primary min-h-11">Edit</button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('settings.integrations.destroy', $profile) }}" class="flex justify-end">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="sf-btn-danger min-h-11">
                            Hapus
                        </button>
                    </form>

                    <div x-show="message" x-cloak class="rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3 text-sm text-gray-700" x-text="message"></div>
                </div>
            </x-sf.card>
        @empty
            <x-sf.empty-state title="Belum ada integrasi" description="Tambahkan profil OCIA, OMEO, atau BESS untuk mulai menghubungkan API eksternal." />
        @endforelse
    </div>
</div>

@push('scripts')
<script>
    function integrationActions() {
        return {
            loading: false,
            message: '',
            async post(url) {
                this.loading = true;
                this.message = '';

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                    });
                    const payload = await response.json();
                    this.message = payload.message || (response.ok ? 'Selesai.' : 'Gagal.');
                } catch (error) {
                    this.message = 'Koneksi gagal atau response tidak valid.';
                } finally {
                    this.loading = false;
                }
            },
        };
    }
</script>
@endpush
@endsection
