@extends('layouts.app')

@section('title', 'Laporan')

@section('content')
<x-sf.page-header title="Laporan" subtitle="Ringkasan dan audit pergerakan stok" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <x-sf.card title="Mutasi Stok" subtitle="Riwayat semua pergerakan stok">
            <a href="{{ route('laporan.mutasi') }}" class="sf-btn-primary min-h-11 w-full mt-2">Buka</a>
        </x-sf.card>

        <x-sf.card title="Spoil & Waste" subtitle="Rekap spoil per periode dan departemen">
            <a href="{{ route('laporan.spoil') }}" class="sf-btn-primary min-h-11 w-full mt-2">Buka</a>
        </x-sf.card>

        <x-sf.card title="Penerimaan Barang" subtitle="Rekap penerimaan per sumber dan supplier">
            <a href="{{ route('laporan.penerimaan') }}" class="sf-btn-primary min-h-11 w-full mt-2">Buka</a>
        </x-sf.card>

        @can('view_all_reports')
            <x-sf.card title="Ringkasan Stok Semua Outlet" subtitle="Breakdown nilai stok per outlet dan kategori">
                <a href="{{ route('laporan.stok-summary') }}" class="sf-btn-primary min-h-11 w-full mt-2">Buka</a>
            </x-sf.card>
        @else
            <x-sf.card title="Stok Outlet" subtitle="Lihat saldo stok outlet Anda">
                <a href="{{ route('stock.balance.index') }}" class="sf-btn-primary min-h-11 w-full mt-2">Buka</a>
            </x-sf.card>
        @endcan
    </div>
</div>
@endsection
