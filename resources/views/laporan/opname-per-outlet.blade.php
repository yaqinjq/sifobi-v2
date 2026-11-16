@extends('layouts.app')

@section('title', 'Laporan Opname per Outlet')

@section('content')
<x-sf.page-header title="Laporan Opname per Outlet" subtitle="1 file per outlet, 1 sheet per departemen, format sama persis form kertas">
    <x-slot:actions>
        @if($outletId)
            <a href="{{ route('laporan.opname-outlet.export', ['outlet_id' => $outletId, 'date' => $date]) }}" class="sf-btn-secondary min-h-11 px-3 text-xs">Export</a>
        @endif
    </x-slot:actions>
</x-sf.page-header>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-3xl mx-auto w-full space-y-4">
    <x-sf.card>
        <form method="GET" action="{{ route('laporan.opname-outlet') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <select name="outlet_id" onchange="this.form.submit()" class="sf-input text-base min-h-11" required @disabled($outlets->count() <= 1)>
                @foreach($outlets as $outlet)
                    <option value="{{ $outlet->id }}" @selected((string) $outletId === (string) $outlet->id)>{{ $outlet->name }}</option>
                @endforeach
            </select>
            <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="sf-input text-base min-h-11">
            <button type="submit" class="sf-btn-primary min-h-11">Tampilkan</button>
        </form>
    </x-sf.card>

    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
        Laporan berisi 1 sheet per departemen yang punya sesi Opname berstatus <strong>Processed</strong> untuk tanggal
        yang dipilih, dengan layout sama persis form kertas (GUDANG/SET OF/kategori sebagai baris, lengkap dengan
        rumus Excel). Kolom <strong>GUDANG Balance</strong> adalah saldo gudang SAAT laporan di-download, bukan
        saldo historis persis tanggal di atas.
    </div>

    @if($outletId)
        <a href="{{ route('laporan.opname-outlet.export', ['outlet_id' => $outletId, 'date' => $date]) }}"
           class="sf-btn-primary min-h-11 w-full inline-flex items-center justify-center">
            Download Laporan ({{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }})
        </a>
    @endif
</div>
@endsection
