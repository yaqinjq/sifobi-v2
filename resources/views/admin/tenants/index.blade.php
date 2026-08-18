@extends('layouts.app')

@section('title', 'Domain Tenant')

@section('content')
<x-sf.page-header title="Domain Tenant" subtitle="Subdomain & domain custom per tenant" back="{{ route('settings.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-4xl mx-auto w-full space-y-5">
    @if(session('success'))
        <div class="rounded-2xl border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-xs text-amber-700">
        Base domain saat ini: <strong>{{ config('app.base_domain') }}</strong>. Subdomain otomatis jadi
        <code class="bg-white px-1 rounded">{subdomain}.{{ config('app.base_domain') }}</code>.
        Setelah disimpan di sini, DNS &amp; SSL untuk domain/subdomain tsb tetap perlu disiapkan manual di server
        (wildcard DNS+SSL untuk subdomain, atau CNAME+SSL terpisah untuk domain custom).
    </div>

    <x-sf.card title="Daftar Tenant" :padding="false">
        <div class="divide-y divide-gray-50">
            @foreach($tenants as $tenant)
                <div class="px-4 py-4" x-data="{ editing: false }">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 truncate">{{ $tenant->name }}</p>
                            <p class="text-xs text-gray-500">{{ $tenant->code }}</p>
                        </div>
                        <button type="button" @click="editing = !editing" class="sf-btn-secondary text-xs min-h-9 shrink-0">
                            <span x-text="editing ? 'Batal' : 'Atur Domain'"></span>
                        </button>
                    </div>

                    <div class="mt-2 flex flex-wrap gap-1.5 text-xs">
                        @if($tenant->subdomain)
                            <span class="badge-active">{{ $tenant->fullSubdomainHost() }}</span>
                        @endif
                        @if($tenant->custom_domain)
                            <span class="badge-blue">{{ $tenant->custom_domain }}</span>
                        @endif
                        @if(! $tenant->subdomain && ! $tenant->custom_domain)
                            <span class="badge-draft">Belum ada domain — pakai domain utama</span>
                        @endif
                    </div>

                    <form x-show="editing" x-cloak method="POST" action="{{ route('admin.tenants.update', $tenant) }}" class="mt-3 space-y-3">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <x-sf.form-group label="Subdomain" :for="'subdomain-'.$tenant->id">
                                <div class="flex items-center gap-1.5">
                                    <input type="text" name="subdomain" id="subdomain-{{ $tenant->id }}" value="{{ $tenant->subdomain }}"
                                           class="sf-input text-base" placeholder="klien-a" maxlength="63">
                                    <span class="text-xs text-gray-400 shrink-0">.{{ config('app.base_domain') }}</span>
                                </div>
                            </x-sf.form-group>
                            <x-sf.form-group label="Domain Custom" :for="'custom_domain-'.$tenant->id">
                                <input type="text" name="custom_domain" id="custom_domain-{{ $tenant->id }}" value="{{ $tenant->custom_domain }}"
                                       class="sf-input text-base" placeholder="app.klien.com" maxlength="191">
                            </x-sf.form-group>
                        </div>
                        <button type="submit" class="sf-btn-primary min-h-10 text-sm">Simpan</button>
                    </form>
                </div>
            @endforeach
        </div>
    </x-sf.card>
</div>
@endsection
