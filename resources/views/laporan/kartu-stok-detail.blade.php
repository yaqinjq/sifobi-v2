@extends('layouts.app')

@section('title', 'Kartu Stok — '.$item->name)

@section('content')
<x-sf.page-header title="Kartu Stok: {{ $item->name }}" subtitle="SKU {{ $item->canonical_sku }}" back="{{ route('laporan.kartu-stok', request()->query()) }}">
    <x-slot:actions>
        <a href="{{ route('laporan.kartu-stok.detail.export', array_merge(['item' => $item->id], request()->query())) }}" class="sf-btn-secondary min-h-11 px-3 text-xs">Export</a>
    </x-slot:actions>
</x-sf.page-header>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-5xl mx-auto w-full space-y-4">
    <x-sf.card>
        <form method="GET" action="{{ route('laporan.kartu-stok.detail', $item) }}" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <select name="outlet_id" class="sf-input text-base min-h-11" required @disabled($outlets->count() <= 1)>
                @foreach($outlets as $outlet)
                    <option value="{{ $outlet->id }}" @selected((string) ($filters['outlet_id'] ?? '') === (string) $outlet->id)>{{ $outlet->name }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? $dateFrom->toDateString() }}" class="sf-input text-base min-h-11">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? $dateTo->toDateString() }}" class="sf-input text-base min-h-11">
            <button type="submit" class="sf-btn-primary min-h-11">Filter</button>
        </form>
    </x-sf.card>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <x-sf.stat label="Saldo Awal" :value="number_format((float) $saldo_awal, 2, ',', '.')" />
        <x-sf.stat label="Saldo Akhir" :value="number_format((float) $saldo_akhir, 2, ',', '.')" />
        <x-sf.stat label="Jumlah Transaksi" :value="$mutations->count()" />
    </div>

    <div class="lg:hidden space-y-3">
        <x-sf.card>
            <p class="text-xs text-gray-500">Saldo Awal ({{ $dateFrom->format('d M Y') }})</p>
            <p class="text-lg font-bold text-gray-900">{{ number_format((float) $saldo_awal, 4, ',', '.') }}</p>
        </x-sf.card>
        @forelse($mutations as $mutation)
            <x-sf.card>
                <div class="flex items-center justify-between gap-3">
                    <span class="badge-draft">{{ $mutation->mutation_type_label }}</span>
                    <span class="text-xs text-gray-500">{{ \Illuminate\Support\Carbon::parse($mutation->performed_at)->format('d M H:i') }}</span>
                </div>
                <div class="flex items-center justify-between mt-2">
                    <p class="font-semibold {{ (float) $mutation->qty_change < 0 ? 'text-red-600' : 'text-green-700' }}">
                        {{ (float) $mutation->qty_change > 0 ? '+' : '' }}{{ number_format((float) $mutation->qty_change, 4, ',', '.') }}
                    </p>
                    <p class="text-sm text-gray-500">Saldo: <span class="font-semibold text-gray-900">{{ number_format((float) $mutation->saldo_berjalan, 4, ',', '.') }}</span></p>
                </div>
                @if($mutation->notes)
                    <p class="text-xs text-gray-500 mt-1">{{ $mutation->notes }}</p>
                @endif
            </x-sf.card>
        @empty
            <x-sf.empty-state title="Belum ada mutasi" description="Tidak ada pergerakan item ini di periode yang dipilih." />
        @endforelse
        <x-sf.card>
            <p class="text-xs text-gray-500">Saldo Akhir ({{ $dateTo->format('d M Y') }})</p>
            <p class="text-lg font-bold text-gray-900">{{ number_format((float) $saldo_akhir, 4, ',', '.') }}</p>
        </x-sf.card>
    </div>

    <div class="hidden lg:block">
        <x-sf.card padding="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Jenis</th>
                            <th class="px-4 py-3">Target</th>
                            <th class="px-4 py-3 text-right">Masuk</th>
                            <th class="px-4 py-3 text-right">Keluar</th>
                            <th class="px-4 py-3 text-right">Saldo Berjalan</th>
                            <th class="px-4 py-3">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        <tr class="bg-gray-50/70 font-semibold">
                            <td class="px-4 py-3" colspan="5">Saldo Awal ({{ $dateFrom->format('d M Y') }})</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $saldo_awal, 4, ',', '.') }}</td>
                            <td class="px-4 py-3"></td>
                        </tr>
                        @forelse($mutations as $mutation)
                            <tr>
                                <td class="px-4 py-3 text-gray-600">{{ \Illuminate\Support\Carbon::parse($mutation->performed_at)->format('d M Y H:i') }}</td>
                                <td class="px-4 py-3"><span class="badge-draft">{{ $mutation->mutation_type_label }}</span></td>
                                <td class="px-4 py-3 text-gray-500">{{ $mutation->stock_target }}</td>
                                <td class="px-4 py-3 text-right text-green-700">{{ (float) $mutation->qty_change > 0 ? '+'.number_format((float) $mutation->qty_change, 4, ',', '.') : '-' }}</td>
                                <td class="px-4 py-3 text-right text-red-600">{{ (float) $mutation->qty_change < 0 ? number_format((float) $mutation->qty_change, 4, ',', '.') : '-' }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-900">{{ number_format((float) $mutation->saldo_berjalan, 4, ',', '.') }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $mutation->notes ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-gray-500">Tidak ada pergerakan di periode ini.</td>
                            </tr>
                        @endforelse
                        <tr class="bg-gray-50/70 font-bold">
                            <td class="px-4 py-3" colspan="5">Saldo Akhir ({{ $dateTo->format('d M Y') }})</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $saldo_akhir, 4, ',', '.') }}</td>
                            <td class="px-4 py-3"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-sf.card>
    </div>
</div>
@endsection
