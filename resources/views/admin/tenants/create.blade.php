@extends('layouts.app')

@section('title', 'Tambah Tenant Baru')

@section('content')
<x-sf.page-header title="Tambah Tenant Baru" subtitle="Buat tenant + akun admin pertamanya" back="{{ route('admin.tenants.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-2xl mx-auto w-full space-y-5">
    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-xs text-amber-700">
        Ini bikin tenant baru + 1 akun admin pertama untuk mereka (role ADMIN,
        dengan role &amp; permission standar SIFOBI sendiri — terpisah dari tenant lain).
        Domain/subdomain diatur belakangan dari halaman "Domain Tenant" setelah ini tersimpan.
    </div>

    <x-sf.card title="Data Tenant">
        <form method="POST" action="{{ route('admin.tenants.store') }}" class="space-y-4">
            @csrf

            <x-sf.form-group label="Nama Tenant" for="name" :required="true">
                <input type="text" name="name" id="name" value="{{ old('name') }}"
                       class="sf-input text-base" placeholder="PT Klien Sejahtera" required maxlength="150">
            </x-sf.form-group>

            <x-sf.form-group label="Kode Tenant" for="code" :required="true"
                              hint="Huruf kapital, angka, underscore saja — dipakai internal, tidak tampil ke user.">
                <input type="text" name="code" id="code" value="{{ old('code') }}"
                       class="sf-input text-base uppercase" placeholder="KLIEN_A" required maxlength="32">
            </x-sf.form-group>

            <div class="border-t border-gray-100 pt-4">
                <p class="text-sm font-semibold text-gray-800 mb-3">Admin Pertama</p>

                <div class="space-y-4">
                    <x-sf.form-group label="Nama Admin" for="admin_name" :required="true">
                        <input type="text" name="admin_name" id="admin_name" value="{{ old('admin_name') }}"
                               class="sf-input text-base" placeholder="Nama penanggung jawab" required maxlength="150">
                    </x-sf.form-group>

                    <x-sf.form-group label="Email Admin" for="admin_email" :required="true"
                                      hint="Password sementara akan dibuatkan otomatis dan ditampilkan sekali setelah disimpan.">
                        <input type="email" name="admin_email" id="admin_email" value="{{ old('admin_email') }}"
                               class="sf-input text-base" placeholder="admin@klien.com" required maxlength="150">
                    </x-sf.form-group>
                </div>
            </div>

            <button type="submit" class="sf-btn-primary w-full min-h-11">
                Buat Tenant &amp; Akun Admin
            </button>
        </form>
    </x-sf.card>
</div>
@endsection
