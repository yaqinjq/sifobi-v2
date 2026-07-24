@extends('layouts.app')

@php
    $fpAppName = $appSetting?->app_name ?? config('app.name', 'SIFOBI');
    $fpAppLogo = $appSetting?->logo_path ? \Illuminate\Support\Facades\Storage::url($appSetting->logo_path) : null;
    $fpAppInitials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $fpAppName) ?: 'SF', 0, 2));
@endphp

@section('title', 'Lupa Password - ' . $fpAppName)
@section('hide-bottom-nav', 'true')

@section('content')
<div class="min-h-screen flex items-center justify-center px-6 py-12 bg-gray-50">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary-800 mb-4">
                @if($fpAppLogo)
                    <img src="{{ $fpAppLogo }}" alt="{{ $fpAppName }}" class="h-11 w-11 object-contain">
                @else
                    <span class="font-heading font-black text-white text-2xl">{{ $fpAppInitials }}</span>
                @endif
            </div>
            <h1 class="font-heading font-bold text-gray-900 text-2xl">Lupa Password</h1>
            <p class="text-gray-500 text-sm mt-1">Masukkan email Anda untuk mendapatkan link reset password</p>
        </div>

        @if(session('status'))
            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 mb-5 flex items-start gap-2">
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
            @csrf

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
                    class="sf-input {{ $errors->has('email') ? 'border-red-400' : '' }}"
                >
                @error('email')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </x-sf.form-group>

            <button type="submit" class="sf-btn-primary w-full text-base">
                Kirim Link Reset Password
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
