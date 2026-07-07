@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

{{-- ══ GREETING SECTION ══ --}}
<div class="px-4 py-5 lg:px-6 lg:py-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            @php
                $hour = now()->hour;
                $greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
            @endphp
            <p class="text-sm text-gray-500">{{ $greeting }},</p>
            <h1 class="font-heading font-bold text-gray-900 text-xl leading-tight mt-0.5">
                {{ $user->name }}
            </h1>
            @if($roles->isNotEmpty())
                <span class="badge-active mt-1.5 inline-flex">{{ $roles->first() }}</span>
            @endif
        </div>
        <div class="shrink-0 w-12 h-12 rounded-2xl bg-primary-800 flex items-center justify-center">
            <span class="font-heading font-bold text-white text-lg">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </span>
        </div>
    </div>
</div>

{{-- ══ KPI GRID ══ --}}
<div class="px-4 lg:px-6">
    <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Ringkasan Hari Ini</h2>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">

        <x-sf.stat
            label="Item Aktif"
            :value="number_format($metrics['item_aktif'])"
            icon="📦"
        />

        <x-sf.stat
            label="Open Stock Hari Ini"
            :value="$metrics['open_stock_today']"
            icon="📋"
            :href="route('operations.open-stocks.index')"
        />

        <x-sf.stat
            label="Stok Menipis"
            :value="$metrics['stok_menipis']"
            icon="⚠️"
            :href="auth()->user()->can('view_stock_balance') ? route('stock.balance.index', ['show_empty' => 1]) : null"
        />

        <x-sf.stat
            label="Spoil Hari Ini"
            :value="$metrics['spoil_today']"
            icon="🗑️"
            :href="route('operations.spoil-wastes.index')"
        />

    </div>
</div>

{{-- ══ QUICK ACTIONS (mobile) ══ --}}
<div class="px-4 mt-6 lg:hidden">
    <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Aksi Cepat</h2>
    <div class="grid grid-cols-2 gap-3">
        <a href="{{ route('operations.open-stocks.create') }}"
           class="sf-card p-4 flex flex-col items-center justify-center gap-2 text-center active:scale-[.98] transition-transform">
            <div class="w-12 h-12 rounded-xl bg-primary-50 flex items-center justify-center text-2xl">📋</div>
            <span class="text-sm font-semibold text-gray-800">Input Open Stock</span>
        </a>
        <a href="{{ auth()->user()->can('view_stock_balance') ? route('stock.balance.index') : route('operations.open-stocks.index') }}"
           class="sf-card p-4 flex flex-col items-center justify-center gap-2 text-center active:scale-[.98] transition-transform">
            <div class="w-12 h-12 rounded-xl bg-primary-50 flex items-center justify-center text-2xl">📦</div>
            <span class="text-sm font-semibold text-gray-800">Lihat Stok</span>
        </a>
        @can('view_goods_receipt')
            <a href="{{ auth()->user()->can('create_goods_receipt') ? route('receiving.goods-receipts.create') : route('receiving.goods-receipts.index') }}"
               class="sf-card p-4 flex flex-col items-center justify-center gap-2 text-center active:scale-[.98] transition-transform">
                <div class="w-12 h-12 rounded-xl bg-primary-50 flex items-center justify-center text-2xl">📥</div>
                <span class="text-sm font-semibold text-gray-800">Penerimaan</span>
            </a>
        @endcan
    </div>
</div>

{{-- ══ OPEN STOCK STATUS BREAKDOWN ══ --}}
<div class="px-4 mt-6 lg:px-6">
    <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Status Open Stock</h2>
    <x-sf.card>
        <div class="flex items-center justify-between py-2">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </div>
                <span class="text-sm text-gray-700 font-medium">Draft</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="badge-draft">{{ $metrics['open_stock_draft'] }}</span>
            </div>
        </div>
        <div class="border-t border-gray-50"></div>
        <div class="flex items-center justify-between py-2">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center">
                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <span class="text-sm text-gray-700 font-medium">Posted</span>
            </div>
            <span class="badge-posted">{{ $metrics['open_stock_posted'] }}</span>
        </div>
        <div class="border-t border-gray-50"></div>
        <div class="flex items-center justify-between py-2">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <span class="text-sm text-gray-700 font-medium">Pending</span>
            </div>
            <span class="badge-pending">{{ $metrics['open_stock_pending'] }}</span>
        </div>
    </x-sf.card>
</div>

{{-- ══ SECONDARY METRICS ══ --}}
<div class="px-4 mt-6 lg:px-6">
    <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Data Sistem</h2>
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
        <x-sf.stat label="Total Outlet"        :value="number_format($metrics['total_outlets'])" />
        <x-sf.stat label="Mutasi Stok"         :value="number_format($metrics['total_stock_mutations'])" />
        <x-sf.stat label="Balance Stok"        :value="number_format($metrics['total_stock_balances'])" />
        <x-sf.stat label="Penerimaan Review"   :value="$metrics['penerimaan_pending']" />
        <x-sf.stat label="Spoil Approval"      :value="$metrics['spoil_pending_approval']" />
        <x-sf.stat label="Opname Draft"         :value="$metrics['opname_draft']" />
    </div>
</div>

{{-- ══ ACTION ITEMS ══ --}}
@if($metrics['penerimaan_pending'] > 0 || $metrics['spoil_pending_approval'] > 0 || $metrics['opname_pending'] > 0)
<div class="px-4 mt-6 lg:px-6">
    <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Perlu Tindakan</h2>
    <x-sf.card>
        <div class="space-y-3">
            @if($metrics['penerimaan_pending'] > 0)
                <a href="{{ route('receiving.goods-receipts.index', ['status' => 'SUBMITTED']) }}" class="flex min-h-11 items-center justify-between gap-3 rounded-xl bg-gray-50 px-3 py-2 text-sm">
                    <span>{{ $metrics['penerimaan_pending'] }} penerimaan perlu approval</span>
                    <span class="font-semibold text-primary-700">Lihat</span>
                </a>
            @endif
            @if($metrics['spoil_pending_approval'] > 0)
                <a href="{{ route('operations.spoil-wastes.index', ['status' => 'PENDING']) }}" class="flex min-h-11 items-center justify-between gap-3 rounded-xl bg-gray-50 px-3 py-2 text-sm">
                    <span>{{ $metrics['spoil_pending_approval'] }} spoil perlu approval</span>
                    <span class="font-semibold text-primary-700">Lihat</span>
                </a>
            @endif
            @if($metrics['opname_pending'] > 0)
                <a href="{{ route('operations.opname.index', ['status' => 'SUBMITTED']) }}" class="flex min-h-11 items-center justify-between gap-3 rounded-xl bg-gray-50 px-3 py-2 text-sm">
                    <span>{{ $metrics['opname_pending'] }} opname perlu approval</span>
                    <span class="font-semibold text-primary-700">Lihat</span>
                </a>
            @endif
        </div>
    </x-sf.card>
</div>
@endif

{{-- ══ LATEST LEDGER ACTIVITY ══ --}}
<div class="px-4 mt-6 lg:px-6">
    <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3">Aktivitas Terbaru</h2>
    <x-sf.card>
        <div class="divide-y divide-gray-100">
            @forelse($latestMutations as $mutation)
                @php
                    $badgeClass = [
                        'PO_RECEIVE' => 'badge-active',
                        'GOODS_RECEIVE' => 'badge-active',
                        'SPOIL_WASTE' => 'badge-rejected',
                        'DAILY_OPNAME_ADJ' => 'badge-pending',
                        'MONTHLY_OPNAME_ADJ' => 'badge-pending',
                        'OPEN_STOCK' => 'badge-draft',
                        'VOID_REVERSAL' => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-gray-100 text-gray-600',
                    ][$mutation->mutation_type] ?? 'badge-draft';
                @endphp
                <div class="py-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-sm text-gray-900 truncate">{{ $mutation->item_name }}</p>
                        <p class="text-xs text-gray-500">
                            {{ \Illuminate\Support\Carbon::parse($mutation->performed_at)->diffForHumans() }}
                            | {{ (float) $mutation->qty_change > 0 ? '+' : '' }}{{ number_format((float) $mutation->qty_change, 4, ',', '.') }}
                        </p>
                    </div>
                    <span class="{{ $badgeClass }}">{{ $mutation->mutation_type }}</span>
                </div>
            @empty
                <div class="py-8 text-center text-sm text-gray-500">Belum ada aktivitas stok.</div>
            @endforelse
        </div>
    </x-sf.card>
</div>

{{-- ══ OPEN STOCK SHORTCUT CARD (desktop) ══ --}}
<div class="hidden lg:block px-6 mt-6">
    <x-sf.card title="Open Stock" subtitle="Draft & yang sudah di-posting hari ini">
        <x-slot:action>
            <a href="{{ route('operations.open-stocks.create') }}" class="sf-btn-primary text-xs px-3 py-1.5 min-h-0">
                + Input Baru
            </a>
        </x-slot:action>

        <div class="flex items-center gap-4">
            <a href="{{ route('operations.open-stocks.index') }}"
               class="sf-btn-secondary text-sm flex-1 justify-center">
                Lihat Semua Open Stock
            </a>
        </div>
    </x-sf.card>
</div>

<div class="h-4 lg:h-8"></div>
@endsection
