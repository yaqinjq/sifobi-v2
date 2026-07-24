<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#1B4332">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $appName = $appSetting?->app_name ?? config('app.name', 'SIFOBI');
        $appTagline = $appSetting?->app_tagline ?? 'Food & Beverage Inventory System';
        $appLogo = $appSetting?->logo_path ? \Illuminate\Support\Facades\Storage::url($appSetting->logo_path) : null;
        $appFavicon = $appSetting?->favicon_path ? \Illuminate\Support\Facades\Storage::url($appSetting->favicon_path) : null;
        $appInitials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $appName) ?: 'SF', 0, 2));
    @endphp

    <title>@yield('title', $appName) - {{ $appName }}</title>

    @if($appFavicon)
        <link rel="icon" type="image/png" href="{{ $appFavicon }}">
        <link rel="apple-touch-icon" href="{{ $appFavicon }}">
    @else
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <link rel="apple-touch-icon" href="/icons/icon-192.png">
    @endif

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full bg-gray-50 font-sans antialiased">

    {{-- ══════════════════════════════════════════
         DESKTOP SIDEBAR (lg+)
    ══════════════════════════════════════════ --}}
    @auth
    <aside class="hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-50 lg:flex lg:w-64 lg:flex-col bg-primary-800">

        {{-- Brand --}}
        <div class="flex h-16 shrink-0 items-center gap-3 px-6 border-b border-primary-700/60">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-700">
                @if($appLogo)
                    <img src="{{ $appLogo }}" alt="{{ $appName }}" class="h-7 w-7 object-contain">
                @else
                    <span class="font-heading font-bold text-white text-sm">{{ $appInitials }}</span>
                @endif
            </div>
            <div>
                <p class="font-heading font-bold text-white text-base leading-tight">{{ $appName }}</p>
                <p class="text-primary-400 text-xs">{{ $appTagline }}</p>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">
            @php
            $sidebarNav = [
                ['route' => 'dashboard',                    'label' => 'Dashboard',         'pattern' => 'dashboard',                    'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',                                                                         'badge_key' => null],
                ['route' => 'operations.open-stocks.index', 'label' => 'Open Stock',        'pattern' => 'operations.open-stocks.*',     'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',                                                                                     'badge_key' => 'open_stock_pending'],
                ['route' => 'stock.balance.index',          'label' => 'Gudang',            'pattern' => 'stock.balance.*',              'permission' => 'view_stock_balance', 'icon' => 'M3 21h18M3 7v14M21 7v14M6 21V7m6 14V7m6 14V7M3 7l9-4 9 4',                                                      'badge_key' => null],
                ['route' => 'operations.opname.index',      'label' => 'Opname',            'pattern' => 'operations.opname.*',          'permission' => 'input_opname',       'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 'badge_key' => 'opname_pending'],
                ['route' => 'operations.spoil-wastes.index','label' => 'Spoil & Waste',     'pattern' => 'operations.spoil-wastes.*',    'permission' => 'record_spoil',       'icon' => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',             'badge_key' => 'spoil_pending'],
                ['route' => 'receiving.goods-receipts.index','label' => 'Penerimaan Barang','pattern' => 'receiving.goods-receipts.*',   'permission' => 'view_goods_receipt', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z',                 'badge_key' => 'receiving_pending'],
                ['route' => 'stock.transfers.index',        'label' => 'Transfer Stok',     'pattern' => 'stock.transfers.*',            'permission' => 'create_stock_transfers', 'icon' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',                                                           'badge_key' => 'transfer_pending'],
            ];
            @endphp

            @foreach($sidebarNav as $item)
            @continue(isset($item['permission']) && ! auth()->user()->can($item['permission']))
            @php $active = request()->routeIs($item['pattern']); @endphp
            <a href="{{ route($item['route']) }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
                      {{ $active
                           ? 'bg-primary-700 text-white'
                           : 'text-primary-300 hover:text-white hover:bg-primary-700/50' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                </svg>
                {{ $item['label'] }}
                @php $badgeCount = $notifBadges[$item['badge_key'] ?? ''] ?? null; @endphp
                @if($badgeCount)
                    <span class="ml-auto bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[1.25rem] text-center leading-tight">
                        {{ $badgeCount > 99 ? '99+' : $badgeCount }}
                    </span>
                @elseif($active)
                    <span class="ml-auto w-1.5 h-1.5 rounded-full bg-primary-400"></span>
                @endif
            </a>
            @endforeach

            @can('view_master_data')
                @php $masterActive = request()->routeIs('master-data.*'); @endphp
                <div x-data="{ open: @js($masterActive) }" class="space-y-0.5">
                    <button type="button"
                            @click="open = !open"
                            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
                                   {{ $masterActive ? 'bg-primary-700 text-white' : 'text-primary-300 hover:text-white hover:bg-primary-700/50' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                        </svg>
                        Master Data
                        <svg class="ml-auto w-4 h-4"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="open" x-collapse class="ml-8 space-y-0.5">
                        <a href="{{ route('master-data.items.index') }}"
                           class="block px-3 py-2 rounded-xl text-sm font-medium transition-colors
                                  {{ request()->routeIs('master-data.items.*') ? 'bg-primary-700 text-white' : 'text-primary-300 hover:text-white hover:bg-primary-700/50' }}">
                            Data Item/Bahan Baku
                        </a>
                        <a href="{{ route('master-data.units.index') }}"
                           class="block px-3 py-2 rounded-xl text-sm font-medium transition-colors
                                  {{ request()->routeIs('master-data.units.*') ? 'bg-primary-700 text-white' : 'text-primary-300 hover:text-white hover:bg-primary-700/50' }}">
                            Satuan (Units)
                        </a>
                        @can('export_master_data')
                            <a href="{{ route('master-data.ie.index') }}"
                               class="block px-3 py-2 rounded-xl text-sm font-medium transition-colors
                                      {{ request()->routeIs('master-data.ie.*') ? 'bg-primary-700 text-white' : 'text-primary-300 hover:text-white hover:bg-primary-700/50' }}">
                                Import / Export
                            </a>
                        @endcan
                    </div>
                </div>
            @endcan

            {{-- Divider --}}
            <div class="my-3 border-t border-primary-700/40"></div>

            @can('view_reports')
                @php $reportsActive = request()->routeIs('laporan.*'); @endphp
                <div x-data="{ open: @js($reportsActive) }" class="space-y-0.5">
                    <button type="button"
                            @click="open = !open"
                            class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
                                   {{ $reportsActive ? 'bg-primary-700 text-white' : 'text-primary-300 hover:text-white hover:bg-primary-700/50' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Laporan
                        <svg class="ml-auto w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="open" x-collapse class="ml-8 space-y-0.5">
                        <a href="{{ route('laporan.mutasi') }}"
                           class="block px-3 py-2 rounded-xl text-sm font-medium transition-colors
                                  {{ request()->routeIs('laporan.mutasi') ? 'bg-primary-700 text-white' : 'text-primary-300 hover:text-white hover:bg-primary-700/50' }}">
                            Mutasi Stok
                        </a>
                        <a href="{{ route('laporan.spoil') }}"
                           class="block px-3 py-2 rounded-xl text-sm font-medium transition-colors
                                  {{ request()->routeIs('laporan.spoil') ? 'bg-primary-700 text-white' : 'text-primary-300 hover:text-white hover:bg-primary-700/50' }}">
                            Spoil & Waste
                        </a>
                        <a href="{{ route('laporan.penerimaan') }}"
                           class="block px-3 py-2 rounded-xl text-sm font-medium transition-colors
                                  {{ request()->routeIs('laporan.penerimaan') ? 'bg-primary-700 text-white' : 'text-primary-300 hover:text-white hover:bg-primary-700/50' }}">
                            Penerimaan
                        </a>
                        @can('view_all_reports')
                            <a href="{{ route('laporan.stok-summary') }}"
                               class="block px-3 py-2 rounded-xl text-sm font-medium transition-colors
                                      {{ request()->routeIs('laporan.stok-summary') ? 'bg-primary-700 text-white' : 'text-primary-300 hover:text-white hover:bg-primary-700/50' }}">
                                Stok Summary
                            </a>
                        @endcan
                    </div>
                </div>
            @endcan

            @can('manage_settings')
                @php $settingsActive = request()->routeIs('settings.*') && ! request()->routeIs('settings.users.*'); @endphp
                @can('manage_users')
                    <a href="{{ route('settings.users.index') }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
                              {{ request()->routeIs('settings.users.*') ? 'bg-primary-700 text-white' : 'text-primary-300 hover:text-white hover:bg-primary-700/50' }}">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m0-4a4 4 0 100-8 4 4 0 000 8zm8 0a4 4 0 100-8 4 4 0 000 8z"/>
                        </svg>
                        Manajemen User
                        @if(request()->routeIs('settings.users.*'))
                            <span class="ml-auto w-1.5 h-1.5 rounded-full bg-primary-400"></span>
                        @endif
                    </a>
                @endcan
                <a href="{{ route('settings.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
                          {{ $settingsActive ? 'bg-primary-700 text-white' : 'text-primary-300 hover:text-white hover:bg-primary-700/50' }}">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Pengaturan
                    @if($settingsActive)
                        <span class="ml-auto w-1.5 h-1.5 rounded-full bg-primary-400"></span>
                    @endif
                </a>
            @endcan
        </nav>

        {{-- User footer --}}
        <div class="shrink-0 border-t border-primary-700/60 p-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="h-9 w-9 rounded-full bg-primary-700 flex items-center justify-center shrink-0">
                    <span class="text-white text-sm font-semibold">
                        {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                    </span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-white text-sm font-medium truncate">{{ auth()->user()->name ?? '—' }}</p>
                    <p class="text-primary-400 text-xs truncate">{{ auth()->user()->email ?? '' }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-xl
                               text-primary-300 hover:text-white hover:bg-primary-700/50
                               text-sm font-medium transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Keluar
                </button>
            </form>
        </div>
    </aside>
    @endauth

    {{-- ══════════════════════════════════════════
         MAIN AREA
    ══════════════════════════════════════════ --}}
    <div class="lg:pl-64 flex flex-col min-h-full">

        {{-- ── MOBILE TOPBAR (hidden on desktop) ── --}}
        @auth
        <div class="lg:hidden sticky top-0 z-40 safe-top bg-primary-800">
            @hasSection('topbar')
                @yield('topbar')
            @else
                <div class="flex items-center gap-3 px-4 py-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-700">
                        @if($appLogo)
                            <img src="{{ $appLogo }}" alt="{{ $appName }}" class="h-7 w-7 object-contain">
                        @else
                            <span class="font-heading font-bold text-white text-sm">{{ $appInitials }}</span>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-heading font-semibold text-white text-base leading-tight">{{ $appName }}</p>
                        <p class="text-primary-300 text-xs truncate">
                            {{ auth()->user()->outlet->name ?? config('app.name') }}
                        </p>
                    </div>
                    @hasSection('topbar-actions')
                    <div class="flex items-center gap-1.5">
                        @yield('topbar-actions')
                    </div>
                    @endif
                </div>
            @endif
        </div>
        @endauth

        {{-- ── FLASH MESSAGES ── --}}
        @if(session('success'))
        <div x-data="{ show: true }"
             x-show="show"
             x-init="setTimeout(() => show = false, 4000)"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-3"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-3"
             class="fixed top-4 inset-x-4 md:left-auto md:right-4 md:max-w-sm z-50
                    bg-green-600 text-white px-4 py-3 rounded-2xl shadow-lg
                    flex items-center gap-3">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <p class="text-sm font-medium flex-1">{{ session('success') }}</p>
            <button @click="show = false" class="text-white/70 hover:text-white shrink-0 -mr-1 p-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        @endif

        @if(session('error'))
        <div x-data="{ show: true }"
             x-show="show"
             x-init="setTimeout(() => show = false, 6000)"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-3"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-3"
             class="fixed top-4 inset-x-4 md:left-auto md:right-4 md:max-w-sm z-50
                    bg-red-600 text-white px-4 py-3 rounded-2xl shadow-lg
                    flex items-center gap-3">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            <p class="text-sm font-medium flex-1">{{ session('error') }}</p>
        </div>
        @endif

        @if(session('warning'))
        <div x-data="{ show: true }"
             x-show="show"
             x-init="setTimeout(() => show = false, 5000)"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-3"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="fixed top-4 inset-x-4 md:left-auto md:right-4 md:max-w-sm z-50
                    bg-accent-500 text-white px-4 py-3 rounded-2xl shadow-lg
                    flex items-center gap-3">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <p class="text-sm font-medium flex-1">{{ session('warning') }}</p>
        </div>
        @endif

        {{-- ── PAGE CONTENT ── --}}
        <main id="main-content"
              class="flex-1 pb-[calc(5rem+env(safe-area-inset-bottom))] lg:pb-8 lg:pt-0">
            @yield('content')
        </main>

        {{-- ── OFFLINE INDICATOR ── --}}
        <div x-data="{ online: navigator.onLine }"
             @online.window="online = true"
             @offline.window="online = false"
             x-show="!online"
             x-transition
             class="fixed bottom-20 inset-x-4 z-50 lg:bottom-4 lg:left-[17rem] lg:right-4
                    bg-accent-500 text-white text-center py-2.5 px-4
                    rounded-2xl text-sm font-medium shadow-lg">
            Offline — Data tersimpan, akan sync saat online
        </div>

        {{-- ── MOBILE BOTTOM NAV ── --}}
        @auth
        @unless($__env->yieldContent('hide-bottom-nav'))
            <x-mobile.bottom-nav />
        @endunless
        @endauth

    </div>

    @stack('scripts')
</body>
</html>
