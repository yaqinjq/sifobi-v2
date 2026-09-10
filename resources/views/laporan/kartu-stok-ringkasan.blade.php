@extends('layouts.app')

@section('title', 'Kartu Stok')

@section('content')
<x-sf.page-header title="Kartu Stok" subtitle="Saldo awal, masuk, keluar, saldo akhir per item">
    <x-slot:actions>
        @if($filters['outlet_id'] ?? null)
            <a href="{{ route('laporan.kartu-stok.export', request()->query()) }}" class="sf-btn-secondary min-h-11 px-3 text-xs">Export</a>
        @endif
    </x-slot:actions>
</x-sf.page-header>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-7xl mx-auto w-full space-y-4">
    <x-sf.card>
        <form method="GET" action="{{ route('laporan.kartu-stok') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <select name="outlet_id" class="sf-input text-base min-h-11" required @disabled($outlets->count() <= 1)>
                @foreach($outlets as $outlet)
                    <option value="{{ $outlet->id }}" @selected((string) ($filters['outlet_id'] ?? '') === (string) $outlet->id)>{{ $outlet->name }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? $dateFrom->toDateString() }}" class="sf-input text-base min-h-11">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? $dateTo->toDateString() }}" class="sf-input text-base min-h-11">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari item/SKU" class="sf-input text-base min-h-11">
            <button type="submit" class="sf-btn-primary min-h-11">Filter</button>
        </form>
    </x-sf.card>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <x-sf.stat label="Total Item Bergerak" :value="$rows->count()" />
        <x-sf.stat label="Item Habis (Saldo 0)" :value="$rows->where('saldo_akhir', '<=', 0)->count()" />
        <x-sf.stat label="Periode" :value="$dateFrom->format('d M').' - '.$dateTo->format('d M Y')" />
    </div>

    <div class="lg:hidden space-y-3">
        @forelse($rows as $row)
            @php $habis = bccomp((string) $row->saldo_akhir, '0', 6) <= 0; @endphp
            <a href="{{ route('laporan.kartu-stok.detail', ['item' => $row->item_id] + request()->query()) }}" class="block">
                <x-sf.card>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $row->item_name }}</p>
                            <p class="text-xs text-gray-500">{{ $row->canonical_sku }}</p>
                        </div>
                        @if($habis)
                            <span class="badge-void">Habis</span>
                        @endif
                    </div>
                    <div class="grid grid-cols-2 gap-2 mt-3 text-sm">
                        <div><span class="text-gray-500">Saldo Awal</span><br>{{ number_format((float) $row->saldo_awal, 2, ',', '.') }} {{ $row->unit }}</div>
                        <div class="text-right"><span class="text-gray-500">Saldo Akhir</span><br><span class="font-bold {{ $habis ? 'text-red-600' : 'text-gray-900' }}">{{ number_format((float) $row->saldo_akhir, 2, ',', '.') }} {{ $row->unit }}</span></div>
                        <div class="text-green-700">Masuk: +{{ number_format((float) $row->total_masuk, 2, ',', '.') }}</div>
                        <div class="text-right text-red-600">Keluar: {{ number_format((float) $row->total_keluar, 2, ',', '.') }}</div>
                    </div>
                </x-sf.card>
            </a>
        @empty
            <x-sf.empty-state title="Tidak ada pergerakan" description="Pilih outlet dan rentang tanggal lain, atau belum ada transaksi di periode ini." />
        @endforelse
    </div>

    <div class="hidden lg:block">
        <x-sf.card padding="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3">Item</th>
                            <th class="px-4 py-3 text-right">Saldo Awal</th>
                            <th class="px-4 py-3 text-right">Masuk</th>
                            <th class="px-4 py-3 text-right">Keluar</th>
                            <th class="px-4 py-3 text-right">Saldo Akhir</th>
                            <th class="px-4 py-3 text-right">Transaksi</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        @forelse($rows as $row)
                            @php $habis = bccomp((string) $row->saldo_akhir, '0', 6) <= 0; @endphp
                            <tr class="{{ $habis ? 'bg-red-50/40' : '' }}">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-900">{{ $row->item_name }}</p>
                                    <p class="text-xs text-gray-500">{{ $row->canonical_sku }}</p>
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $row->saldo_awal, 4, ',', '.') }} {{ $row->unit }}</td>
                                <td class="px-4 py-3 text-right text-green-700">+{{ number_format((float) $row->total_masuk, 4, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-red-600">{{ number_format((float) $row->total_keluar, 4, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-bold {{ $habis ? 'text-red-600' : 'text-gray-900' }}">
                                    {{ number_format((float) $row->saldo_akhir, 4, ',', '.') }} {{ $row->unit }}
                                    @if($habis)<span class="badge-void ml-1">Habis</span>@endif
                                </td>
                                <td class="px-4 py-3 text-right text-gray-500">{{ $row->jumlah_transaksi }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('laporan.kartu-stok.detail', ['item' => $row->item_id] + request()->query()) }}" class="text-primary-700 underline text-xs">Lihat Kartu</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-gray-500">Tidak ada pergerakan di periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-sf.card>
    </div>
</div>
@endsection
