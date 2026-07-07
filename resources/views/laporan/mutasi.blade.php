@extends('layouts.app')

@section('title', 'Laporan Mutasi Stok')

@section('content')
<x-sf.page-header title="Laporan Mutasi Stok" subtitle="Pergerakan ledger immutable">
    <x-slot:actions>
        <a href="{{ route('laporan.mutasi.export', request()->query()) }}" class="sf-btn-secondary min-h-11 px-3 text-xs">Export</a>
    </x-slot:actions>
</x-sf.page-header>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-7xl mx-auto w-full space-y-4">
    <x-sf.card>
        <form method="GET" action="{{ route('laporan.mutasi') }}" class="grid grid-cols-1 md:grid-cols-6 gap-3">
            <select name="outlet_id" class="sf-input text-base min-h-11">
                <option value="">Semua outlet</option>
                @foreach($outlets as $outlet)
                    <option value="{{ $outlet->id }}" @selected((string) ($filters['outlet_id'] ?? '') === (string) $outlet->id)>{{ $outlet->name }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? now()->startOfMonth()->toDateString() }}" class="sf-input text-base min-h-11">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? now()->toDateString() }}" class="sf-input text-base min-h-11">
            <select name="mutation_type" class="sf-input text-base min-h-11">
                <option value="">Semua tipe</option>
                @foreach($mutationTypes as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['mutation_type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari item/SKU" class="sf-input text-base min-h-11">
            <button type="submit" class="sf-btn-primary min-h-11">Filter</button>
        </form>
    </x-sf.card>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <x-sf.stat label="Masuk" :value="'+'.number_format((float) ($summary->total_in ?? 0), 4, ',', '.')" />
        <x-sf.stat label="Keluar" :value="number_format((float) ($summary->total_out ?? 0), 4, ',', '.')" />
        <x-sf.stat label="Net" :value="number_format((float) ($summary->net_qty ?? 0), 4, ',', '.')" />
    </div>

    <div class="lg:hidden space-y-3">
        @forelse($mutations as $mutation)
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
            <x-sf.card>
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <span class="{{ $badgeClass }}">{{ $mutation->mutation_type }}</span>
                        <span class="text-xs text-gray-500">{{ \Illuminate\Support\Carbon::parse($mutation->performed_at)->format('d M H:i') }}</span>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">{{ $mutation->item_name }}</p>
                        <p class="text-xs text-gray-500">SKU: {{ $mutation->canonical_sku }} | {{ $mutation->outlet_name }}</p>
                    </div>
                    <p class="text-lg font-bold {{ (float) $mutation->qty_change < 0 ? 'text-red-600' : 'text-green-700' }}">
                        {{ (float) $mutation->qty_change > 0 ? '+' : '' }}{{ number_format((float) $mutation->qty_change, 4, ',', '.') }} {{ $mutation->unit }}
                    </p>
                    <p class="text-xs text-gray-500">{{ $mutation->notes ?: '-' }}</p>
                </div>
            </x-sf.card>
        @empty
            <x-sf.empty-state title="Belum ada mutasi" description="Ledger stok akan muncul setelah transaksi diposting." />
        @endforelse
    </div>

    <div class="hidden lg:block">
        <x-sf.card padding="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3">Waktu</th>
                            <th class="px-4 py-3">Tipe</th>
                            <th class="px-4 py-3">Item</th>
                            <th class="px-4 py-3">Outlet</th>
                            <th class="px-4 py-3 text-right">Qty Change</th>
                            <th class="px-4 py-3">Unit</th>
                            <th class="px-4 py-3">Referensi</th>
                            <th class="px-4 py-3">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        @forelse($mutations as $mutation)
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
                            <tr>
                                <td class="px-4 py-3 text-gray-600">{{ \Illuminate\Support\Carbon::parse($mutation->performed_at)->format('d M Y H:i') }}</td>
                                <td class="px-4 py-3"><span class="{{ $badgeClass }}">{{ $mutation->mutation_type }}</span></td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-900">{{ $mutation->item_name }}</p>
                                    <p class="text-xs text-gray-500">{{ $mutation->canonical_sku }}</p>
                                </td>
                                <td class="px-4 py-3">{{ $mutation->outlet_name }}</td>
                                <td class="px-4 py-3 text-right font-semibold {{ (float) $mutation->qty_change < 0 ? 'text-red-600' : 'text-green-700' }}">
                                    {{ (float) $mutation->qty_change > 0 ? '+' : '' }}{{ number_format((float) $mutation->qty_change, 4, ',', '.') }}
                                </td>
                                <td class="px-4 py-3">{{ $mutation->unit }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500">#{{ $mutation->reference_id ?? '-' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $mutation->notes ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-gray-500">Belum ada mutasi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-sf.card>
    </div>

    {{ $mutations->links() }}
</div>
@endsection
