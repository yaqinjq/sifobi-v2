@extends('layouts.app')

@php
    $rpAppName = $appSetting?->app_name ?? config('app.name', 'SIFOBI');
    $rpAppLogo = $appSetting?->logo_path ? \Illuminate\Support\Facades\Storage::url($appSetting->logo_path) : null;
    $rpAppInitials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $rpAppName) ?: 'SF', 0, 2));
@endphp

@section('title', 'Reset Password - ' . $rpAppName)
@section('hide-bottom-nav', 'true')

@section('content')
<div class="min-h-screen flex items-center justify-center px-6 py-12 bg-gray-50">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary-800 mb-4">
                @if($rpAppLogo)
                    <img src="{{ $rpAppLogo }}" alt="{{ $rpAppName }}" class="h-11 w-11 object-contain">
                @else
                    <span class="font-heading font-black text-white text-2xl">{{ $rpAppInitials }}</span>
                @endif
            </div>
            <h1 class="font-heading font-bold text-gray-900 text-2xl">Reset Password</h1>
            <p class="text-gray-500 text-sm mt-1">Masukkan password baru Anda</p>
        </div>

        <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <x-sf.form-group label="Email" for="email" :required="true">
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email', $email) }}"
                    autocomplete="email"
                    required
                    placeholder="nama@outlet.com"
                    class="sf-input {{ $errors->has('email') ? 'border-red-400' : '' }}"
                >
                @error('email')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </x-sf.form-group>

            <x-sf.form-group label="Password Baru" for="password" :required="true">
                <div class="relative" x-data="{ show: false }">
                    <input
                        :type="show ? 'text' : 'password'"
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        required
                        placeholder="Minimal 8 karakter"
                        class="sf-input pr-11 {{ $errors->has('password') ? 'border-red-400' : '' }}"
                    >
                    <button type="button"
                            @click="show = !show"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i :class="show ? 'ti ti-eye-off' : 'ti ti-eye'" class="text-base"></i>
                    </button>
                </div>
                @error('password')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </x-sf.form-group>

            <x-sf.form-group label="Konfirmasi Password Baru" for="password_confirmation" :required="true">
                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    required
                    placeholder="Ulangi password baru"
                    class="sf-input"
                >
            </x-sf.form-group>

            @if($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 flex items-start gap-2">
                    <svg class="w-4 h-4 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    {{ $errors->first() }}
                </div>
            @endif

            <button type="submit" class="sf-btn-primary w-full text-base">
                Reset Password
            </button>
        </form>

        <div class="text-center mt-6">
            <a href="{{ route('login') }}" class="text-sm text-primary-700 hover:underline font-medium">
                &larr; Kembali ke halaman login
            </a>
        </div>
    </div>
</div>
@endsection
