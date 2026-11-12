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
<div class="min-h-screen flex items-center justify-center bg-gray-50 relative overflow-hidden px-4 py-12">

    {{-- Dekorasi blur lime, CSS murni (bukan gambar) --}}
    <div class="pointer-events-none absolute -top-24 -left-24 w-80 h-80 rounded-full bg-lime-300/40 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-24 -right-24 w-96 h-96 rounded-full bg-lime-400/30 blur-3xl"></div>

    <div class="relative z-10 w-full max-w-sm">
        <div class="sf-card p-8">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary-800 mb-4">
                    @if($loginAppLogo)
                        <img src="{{ $loginAppLogo }}" alt="{{ $loginAppName }}" class="h-11 w-11 object-contain">
                    @else
                        <span class="font-heading font-black text-white text-2xl">{{ $loginAppInitials }}</span>
                    @endif
                </div>
                <h1 class="font-heading font-bold text-gray-900 text-2xl">{{ $loginAppName }}</h1>
                <p class="text-gray-500 text-sm mt-1">{{ $loginAppTagline }}</p>
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

                {{-- Remember + Lupa Password --}}
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                            class="w-4 h-4 rounded border-gray-300 text-primary-700 focus:ring-primary-500"
                        >
                        <span class="text-sm text-gray-700">Ingat saya di perangkat ini</span>
                    </label>
                    <a href="{{ route('password.request') }}" class="text-sm font-medium text-primary-700 hover:text-primary-800">
                        Lupa password?
                    </a>
                </div>

                {{-- Validation error --}}
                @if($errors->any())
                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 flex items-start gap-2">
                        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        {{ $errors->first() }}
                    </div>

                    @if(str_contains($errors->first(), 'belum diverifikasi'))
                        <form method="POST" action="{{ route('register.resend-verification') }}">
                            @csrf
                            <input type="hidden" name="email" value="{{ old('email') }}">
                            <button type="submit" class="text-sm font-medium text-primary-700 hover:text-primary-800">
                                Kirim ulang link verifikasi
                            </button>
                        </form>
                    @endif
                @endif

                @if(session('success'))
                    <div class="rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-700">
                        {{ session('success') }}
                    </div>
                @endif

                {{-- Submit --}}
                <button type="submit" class="sf-btn-primary w-full text-base">
                    Masuk ke {{ $loginAppName }}
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-gray-400 mt-8">
            &copy; {{ date('Y') }} MKO Group
        </p>
    </div>
</div>
@endsection
