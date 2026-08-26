@extends('layouts.app')

@php
    $regAppName = $appSetting?->app_name ?? config('app.name', 'SIFOBI');
    $regAppLogo = $appSetting?->logo_path ? \Illuminate\Support\Facades\Storage::url($appSetting->logo_path) : null;
    $regAppInitials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $regAppName) ?: 'SF', 0, 2));
    $registeredEmail = session('registered_email');
@endphp

@section('title', 'Daftar - ' . $regAppName)
@section('hide-bottom-nav', 'true')

@section('content')
<div class="min-h-screen flex">

    {{-- ── LEFT PANEL (desktop only) ── --}}
    <div class="hidden lg:flex lg:w-1/2 xl:w-2/5 bg-primary-800 flex-col items-center justify-center p-12 relative overflow-hidden">
        <div class="absolute inset-0 opacity-5">
            <div class="absolute top-10 left-10 w-64 h-64 rounded-full border-2 border-white"></div>
            <div class="absolute bottom-20 right-10 w-96 h-96 rounded-full border border-white"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-48 h-48 rounded-full border-4 border-white"></div>
        </div>

        <div class="relative z-10 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-3xl bg-primary-700 mb-8">
                @if($regAppLogo)
                    <img src="{{ $regAppLogo }}" alt="{{ $regAppName }}" class="h-14 w-14 object-contain">
                @else
                    <span class="font-heading font-black text-white text-3xl">{{ $regAppInitials }}</span>
                @endif
            </div>
            <h1 class="font-heading font-black text-white text-4xl mb-3">{{ $regAppName }}</h1>
            <p class="text-primary-300 text-lg font-medium mb-8">Coba Gratis 14 Hari</p>
            <div class="space-y-3 text-left max-w-xs mx-auto">
                @foreach(['Semua fitur langsung aktif, tanpa kartu kredit', 'Data Anda aman & terpisah per tenant', 'Berhenti kapan saja selama masa trial'] as $feat)
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

    {{-- ── RIGHT PANEL ── --}}
    <div class="flex-1 flex flex-col items-center justify-center px-6 py-12 lg:px-12 bg-gray-50">
        <div class="lg:hidden text-center mb-10">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary-800 mb-4">
                @if($regAppLogo)
                    <img src="{{ $regAppLogo }}" alt="{{ $regAppName }}" class="h-11 w-11 object-contain">
                @else
                    <span class="font-heading font-black text-white text-2xl">{{ $regAppInitials }}</span>
                @endif
            </div>
            <h1 class="font-heading font-black text-gray-900 text-3xl">{{ $regAppName }}</h1>
            <p class="text-gray-500 text-sm mt-1">Coba Gratis 14 Hari</p>
        </div>

        <div class="w-full max-w-md">
            @if($registeredEmail)
                {{-- ── STATE: cek email ── --}}
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-primary-100 mb-5">
                        <i class="ti ti-mail-opened text-2xl text-primary-700" aria-hidden="true"></i>
                    </div>
                    <h2 class="font-heading font-bold text-gray-900 text-2xl mb-2">Cek email Anda</h2>
                    <p class="text-gray-500 text-sm mb-6">
                        Kami sudah kirim link verifikasi ke
                        <span class="font-semibold text-gray-700">{{ $registeredEmail }}</span>.
                        Klik link di email itu untuk mengaktifkan akun trial Anda.
                    </p>

                    @if(session('success'))
                        <div class="rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-700 mb-4">
                            {{ session('success') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register.resend-verification') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="email" value="{{ $registeredEmail }}">
                        <button type="submit" class="sf-btn-secondary w-full text-sm">Kirim ulang email verifikasi</button>
                    </form>

                    <p class="text-center text-sm text-gray-400 mt-6">
                        <a href="{{ route('login') }}" class="text-primary-700 font-medium">Kembali ke halaman masuk</a>
                    </p>
                </div>
            @else
                {{-- ── STATE: form daftar ── --}}
                <div class="mb-8 lg:block hidden">
                    <h2 class="font-heading font-bold text-gray-900 text-2xl">Daftar tenant baru</h2>
                    <p class="text-gray-500 text-sm mt-1">Mulai trial gratis 14 hari, tanpa kartu kredit</p>
                </div>

                <form method="POST" action="{{ route('register.store') }}" class="space-y-5">
                    @csrf

                    <x-sf.form-group label="Nama Usaha" for="business_name" :required="true">
                        <input type="text" id="business_name" name="business_name" value="{{ old('business_name') }}"
                               required placeholder="Contoh: Kedai Kopi Nusantara" class="sf-input">
                    </x-sf.form-group>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-sf.form-group label="Nama Anda" for="admin_name" :required="true">
                            <input type="text" id="admin_name" name="admin_name" value="{{ old('admin_name') }}"
                                   required placeholder="Nama lengkap" class="sf-input">
                        </x-sf.form-group>

                        <x-sf.form-group label="No. HP/WA" for="business_phone" :required="true">
                            <input type="text" id="business_phone" name="business_phone" value="{{ old('business_phone') }}"
                                   required placeholder="08xxxxxxxxxx" class="sf-input">
                        </x-sf.form-group>
                    </div>

                    <x-sf.form-group label="Email" for="admin_email" :required="true">
                        <input type="email" id="admin_email" name="admin_email" value="{{ old('admin_email') }}"
                               required placeholder="nama@usaha.com" class="sf-input">
                    </x-sf.form-group>

                    <x-sf.form-group label="Alamat Usaha" for="business_address" :required="true">
                        <textarea id="business_address" name="business_address" required rows="2"
                                  placeholder="Alamat lengkap usaha Anda" class="sf-input">{{ old('business_address') }}</textarea>
                    </x-sf.form-group>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-sf.form-group label="Password" for="password" :required="true">
                            <input type="password" id="password" name="password" required minlength="8"
                                   placeholder="Minimal 8 karakter" class="sf-input">
                        </x-sf.form-group>

                        <x-sf.form-group label="Ulangi Password" for="password_confirmation" :required="true">
                            <input type="password" id="password_confirmation" name="password_confirmation" required
                                   placeholder="Ulangi password" class="sf-input">
                        </x-sf.form-group>
                    </div>

                    <x-sf.form-group label="Pilih Paket" for="plan" :required="true">
                        <div class="grid grid-cols-3 gap-2">
                            @foreach(['STARTER' => 'Starter', 'GROWTH' => 'Growth', 'ENTERPRISE' => 'Enterprise'] as $value => $label)
                                <label class="flex items-center justify-center gap-2 border rounded-xl px-3 py-2.5 text-sm font-medium cursor-pointer transition-colors has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50 has-[:checked]:text-primary-700 border-gray-200 text-gray-600">
                                    <input type="radio" name="plan" value="{{ $value }}" class="sr-only"
                                           {{ old('plan', 'STARTER') === $value ? 'checked' : '' }} required>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </x-sf.form-group>

                    @if($errors->any())
                        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <ul class="list-disc list-inside space-y-1">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <button type="submit" class="sf-btn-primary w-full text-base">
                        Mulai Coba Gratis
                    </button>
                </form>

                <p class="text-center text-sm text-gray-400 mt-6">
                    Sudah punya akun? <a href="{{ route('login') }}" class="text-primary-700 font-medium">Masuk di sini</a>
                </p>
            @endif
        </div>
    </div>
</div>
@endsection
