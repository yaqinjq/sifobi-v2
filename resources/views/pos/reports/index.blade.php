@extends('layouts.app')

@section('title', 'Laporan Penjualan POS')

@section('content')
<x-sf.page-header
    title="Laporan Penjualan POS"
    subtitle="Ringkasan penjualan per periode"
    back="{{ route('dashboard') }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-4xl mx-auto w-full space-y-5">
    <form method="GET" class="flex flex-wrap items-end gap-2">
        <div>
            <label class="text-xs text-gray-500">Outlet</label>
            <select name="outlet_id" class="sf-input text-sm">
                <option value="">Semua Outlet</option>
                @foreach($outlets as $o)
                    <option value="{{ $o->id }}" @selected($o->id == $outletId)>{{ $o->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-500">Dari Tanggal</label>
            <input type="date" name="date_from" value="{{ $dateFrom->toDateString() }}" class="sf-input text-sm">
        </div>
        <div>
            <label class="text-xs text-gray-500">Sampai Tanggal</label>
            <input type="date" name="date_to" value="{{ $dateTo->toDateString() }}" class="sf-input text-sm">
        </div>
        <button type="submit" class="sf-btn-secondary min-h-11 px-4">Tampilkan</button>
        <a href="{{ route('pos.reports.export', request()->only('outlet_id', 'date_from', 'date_to')) }}" class="sf-btn-primary inline-flex min-h-11 items-center gap-2 px-4">
            <i class="ti ti-download text-base" aria-hidden="true"></i>
            <span>Export Excel</span>
        </a>
    </form>

    <div class="grid grid-cols-2 gap-3">
        <x-sf.stat label="Total Omzet" value="Rp {{ number_format((float) $totalOmzet, 0, ',', '.') }}" />
        <x-sf.stat label="Jumlah Order" value="{{ $totalOrders }}" />
    </div>

    <x-sf.card title="Per Outlet">
        @if($byOutlet->isEmpty())
            <p class="text-sm text-gray-400 py-4 text-center">Belum ada transaksi lunas di periode ini.</p>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($byOutlet as $row)
                    <div class="flex items-center justify-between py-2.5 text-sm">
                        <span class="text-gray-700">{{ $row['outlet']->name ?? '-' }} <span class="text-gray-400">({{ $row['count'] }} order)</span></span>
                        <span class="font-semibold text-gray-900">Rp {{ number_format((float) $row['total'], 0, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-sf.card>

    <x-sf.card title="Per Metode Pembayaran">
        @if($byMethod->isEmpty())
            <p class="text-sm text-gray-400 py-4 text-center">Belum ada transaksi lunas di periode ini.</p>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($byMethod as $row)
                    <div class="flex items-center justify-between py-2.5 text-sm">
                        <span class="text-gray-700">{{ \App\Modules\Pos\Models\PosPayment::METHOD_LABELS[$row->method] ?? $row->method }} <span class="text-gray-400">({{ $row->total_tx }}x)</span></span>
                        <span class="font-semibold text-gray-900">Rp {{ number_format((float) $row->total, 0, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-sf.card>
</div>
@endsection
