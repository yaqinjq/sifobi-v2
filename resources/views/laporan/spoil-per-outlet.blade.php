@extends('layouts.app')

@section('title', 'Laporan Spoil & Waste per Outlet')

@section('content')
<x-sf.page-header title="Laporan Spoil & Waste per Outlet" subtitle="1 file per outlet, 1 sheet per departemen, format sama persis form kertas">
    <x-slot:actions>
        @if($outletId)
            <a href="{{ route('laporan.spoil-outlet.export', ['outlet_id' => $outletId, 'date_from' => $dateFrom->toDateString(), 'date_to' => $dateTo->toDateString()]) }}" class="sf-btn-secondary min-h-11 px-3 text-xs">Export</a>
        @endif
    </x-slot:actions>
</x-sf.page-header>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-3xl mx-auto w-full space-y-4">
    <x-sf.card>
        <form method="GET" action="{{ route('laporan.spoil-outlet') }}" class="flex flex-wrap items-center gap-3">
            <select name="outlet_id" onchange="this.form.submit()" class="sf-input text-base min-h-11 flex-1 min-w-[160px]" required @disabled($outlets->count() <= 1)>
                @foreach($outlets as $outlet)
                    <option value="{{ $outlet->id }}" @selected((string) $outletId === (string) $outlet->id)>{{ $outlet->name }}</option>
                @endforeach
            </select>
            <div class="shrink-0 w-56">
                <x-sf.date-range-picker name="date" :from="$dateFrom->toDateString()" :to="$dateTo->toDateString()" />
            </div>
            <button type="submit" class="sf-btn-primary min-h-11">Tampilkan</button>
        </form>
    </x-sf.card>

    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
        Laporan berisi 1 sheet per departemen yang punya catatan Spoil & Waste berstatus <strong>Approved</strong>
        dalam rentang tanggal yang dipilih, dengan layout sama persis form kertas (TGL per baris, kategori sebagai
        baris, lengkap dengan rumus Total Harga).
    </div>

    @if($outletId)
        <a href="{{ route('laporan.spoil-outlet.export', ['outlet_id' => $outletId, 'date_from' => $dateFrom->toDateString(), 'date_to' => $dateTo->toDateString()]) }}"
           class="sf-btn-primary min-h-11 w-full inline-flex items-center justify-center">
            Download Laporan ({{ $dateFrom->format('d M') }} - {{ $dateTo->format('d M Y') }})
        </a>
    @endif
</div>
@endsection
