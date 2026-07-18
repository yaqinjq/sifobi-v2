@extends('layouts.app')

@php
    $loginAppName = $appSetting?->app_name ?? config('app.name', 'SIFOBI');
    $loginAppTagline = $appSetting?->app_tagline ?? 'Food & Beverage Inventory System';
    $loginAppLogo = $appSetting?->logo_path ? \Illuminate\Support\Facades\Storage::url($appSetting->logo_path) : null;
    $loginAppInitials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $loginAppName) ?: 'SF', 0, 2));
@endphp

@section('title', 'Masuk - ' . $loginAppName)
@section('hide-bottom-nav', 'true')

@section('content')
<div class="min-h-screen flex">

    {{-- ── LEFT PANEL (desktop only) ── --}}
    <div class="hidden lg:flex lg:w-1/2 xl:w-2/5 bg-primary-800 flex-col items-center justify-center p-12 relative overflow-hidden">
        {{-- Background pattern --}}
        <div class="absolute inset-0 opacity-5">
            <div class="absolute top-10 left-10 w-64 h-64 rounded-full border-2 border-white"></div>
            <div class="absolute bottom-20 right-10 w-96 h-96 rounded-full border border-white"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-48 h-48 rounded-full border-4 border-white"></div>
        </div>

        <div class="relative z-10 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-primary-700 mb-8">
                @if($loginAppLogo)
                    <img src="{{ $loginAppLogo }}" alt="{{ $loginAppName }}" class="h-14 w-14 object-contain">
                @else
                    <span class="font-heading font-black text-white text-3xl">{{ $loginAppInitials }}</span>
                @endif
            </div>
            <h1 class="font-heading font-black text-white text-4xl mb-3">{{ $loginAppName }}</h1>
            <p class="text-primary-300 text-lg font-medium mb-8">{{ $loginAppTagline }}</p>
            <div class="space-y-3 text-left max-w-xs mx-auto">
                @foreach(['Kelola stok bahan baku secara real-time', 'Pantau penerimaan barang & spoilage', 'Laporan harian otomatis per outlet'] as $feat)
                <div class="flex items-center gap-3">
                    <div class="w-5 h-5 rounded-full bg-primary-600 flex items-center justify-center shrink-0">
                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <p class="text-primary-200 text-sm">{{ $feat }}</p>
                </div>
                @endforeach
            </div>
        </div>

        <p class="relative z-10 absolute bottom-8 text-primary-500 text-xs">
            &copy; {{ date('Y') }} MKO Group
        </p>
    </div>

    {{-- ── RIGHT PANEL — login form ── --}}
    <div class="flex-1 flex flex-col items-center justify-center px-6 py-12 lg:px-12 bg-gray-50">
        {{-- Mobile logo --}}
        <div class="lg:hidden text-center mb-10">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary-800 mb-4">
                @if($loginAppLogo)
                    <img src="{{ $loginAppLogo }}" alt="{{ $loginAppName }}" class="h-11 w-11 object-contain">
                @else
                    <span class="font-heading font-black text-white text-2xl">{{ $loginAppInitials }}</span>
                @endif
            </div>
            <h1 class="font-heading font-black text-gray-900 text-3xl">{{ $loginAppName }}</h1>
            <p class="text-gray-500 text-sm mt-1">{{ $loginAppTagline }}</p>
        </div>

        <div class="w-full max-w-sm">
            <div class="mb-8 lg:block hidden">
                <h2 class="font-heading font-bold text-gray-900 text-2xl">Selamat datang</h2>
                <p class="text-gray-500 text-sm mt-1">Masuk ke akun {{ $loginAppName }} Anda</p>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                @csrf

                {{-- Email --}}
                <x-sf.form-group label="Email" for="email" :required="true">
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        required
                        autofocus
                        placeholder="nama@outlet.com"
                        class="sf-input"
                    >
                </x-sf.form-group>

                {{-- Password --}}
                <x-sf.form-group label="Password" for="password" :required="true">
                    <div class="relative" x-data="{ show: false }">
                        <input
                            :type="show ? 'text' : 'password'"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required
                            placeholder="••••••••"
                            class="sf-input pr-11"
                        >
                        <button type="button"
                                @click="show = !show"
                                :title="show ? 'Sembunyikan password' : 'Tampilkan password'"
                                aria-label="Toggle password visibility"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                            <i :class="show ? 'ti ti-eye-off' : 'ti ti-eye'" class="text-base" aria-hidden="true"></i>
                        </button>
                    </div>
                </x-sf.form-group>

                {{-- Remember --}}
                <label class="flex items-center gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        name="remember"
                        value="1"
                        class="w-4 h-4 rounded border-gray-300 text-primary-700 focus:ring-primary-500"
                    >
                    <span class="text-sm text-gray-700">Ingat saya di perangkat ini</span>
                </label>

                {{-- Validation error --}}
                @if($errors->any())
                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 flex items-start gap-2">
                        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        {{ $errors->first() }}
                    </div>
                @endif

                {{-- Submit --}}
                <button type="submit" class="sf-btn-primary w-full text-base">
                    Masuk ke {{ $loginAppName }}
                </button>
            </form>

            <p class="text-center text-xs text-gray-400 mt-10 lg:hidden">
                &copy; {{ date('Y') }} MKO Group
            </p>
        </div>
    </div>
</div>
@endsection
