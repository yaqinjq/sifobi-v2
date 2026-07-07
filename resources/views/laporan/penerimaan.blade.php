@extends('layouts.app')

@section('title', 'Laporan Penerimaan Barang')

@section('content')
<x-sf.page-header title="Laporan Penerimaan Barang" subtitle="Rekap penerimaan per sumber dan supplier">
    <x-slot:actions>
        <a href="{{ route('laporan.penerimaan.export', request()->query()) }}" class="sf-btn-secondary min-h-11 px-3 text-xs">Export</a>
    </x-slot:actions>
</x-sf.page-header>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-7xl mx-auto w-full space-y-4">
    <x-sf.card>
        <form method="GET" action="{{ route('laporan.penerimaan') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <select name="outlet_id" class="sf-input text-base min-h-11">
                <option value="">Semua outlet</option>
                @foreach($outlets as $outlet)
                    <option value="{{ $outlet->id }}" @selected((string) ($filters['outlet_id'] ?? '') === (string) $outlet->id)>{{ $outlet->name }}</option>
                @endforeach
            </select>
            <select name="source" class="sf-input text-base min-h-11">
                <option value="">Semua sumber</option>
                @foreach($sources as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['source'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? now()->startOfMonth()->toDateString() }}" class="sf-input text-base min-h-11">
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? now()->toDateString() }}" class="sf-input text-base min-h-11">
            <button type="submit" class="sf-btn-primary min-h-11">Filter</button>
        </form>
    </x-sf.card>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <x-sf.stat label="Total GR" :value="number_format((int) ($summary->total_receipts ?? 0))" />
        <x-sf.stat label="Total Item" :value="number_format((int) ($summary->total_items ?? 0))" />
        <x-sf.stat label="Total Nilai" :value="'Rp '.number_format((float) ($summary->total_value ?? 0), 0, ',', '.')" />
    </div>

    <div class="lg:hidden space-y-3">
        @forelse($receivings as $row)
            @php
                $sourceClass = [
                    'OCIA_PO' => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-amber-100 text-amber-800',
                    'WIP_CENTRAL_KITCHEN' => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-orange-100 text-orange-800',
                    'PURCHASING_DRYGOOD' => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-blue-100 text-blue-800',
                    'SUPPLIER_LUAR' => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-purple-100 text-purple-800',
                ][$row->source] ?? 'badge-draft';
            @endphp
            <x-sf.card>
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <span class="{{ $sourceClass }}">{{ $sources[$row->source] ?? $row->source }}</span>
                        <span class="badge-posted">{{ $row->status }}</span>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">{{ $row->code }}</p>
                        <p class="text-xs text-gray-500">{{ \Illuminate\Support\Carbon::parse($row->receipt_date)->format('d M Y') }} | {{ $row->outlet_name }}</p>
                    </div>
                    <p class="text-sm text-gray-700">{{ $row->item_name }}</p>
                    <p class="text-sm text-gray-500">{{ number_format((float) $row->qty_received, 4, ',', '.') }} {{ $row->unit }} | Rp {{ number_format((float) $row->total_value, 0, ',', '.') }}</p>
                </div>
            </x-sf.card>
        @empty
            <x-sf.empty-state title="Belum ada penerimaan" description="Data penerimaan akan tampil sesuai filter periode." />
        @endforelse
    </div>

    <div class="hidden lg:block">
        <x-sf.card padding="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3">Kode GR</th>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">Sumber</th>
                            <th class="px-4 py-3">Supplier/Ref</th>
                            <th class="px-4 py-3">Outlet</th>
                            <th class="px-4 py-3">Item</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3 text-right">Harga</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        @forelse($receivings as $row)
                            @php
                                $sourceClass = [
                                    'OCIA_PO' => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-amber-100 text-amber-800',
                                    'WIP_CENTRAL_KITCHEN' => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-orange-100 text-orange-800',
                                    'PURCHASING_DRYGOOD' => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-blue-100 text-blue-800',
                                    'SUPPLIER_LUAR' => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-purple-100 text-purple-800',
                                ][$row->source] ?? 'badge-draft';
                            @endphp
                            <tr>
                                <td class="px-4 py-3 font-semibold text-gray-900">{{ $row->code }}</td>
                                <td class="px-4 py-3">{{ \Illuminate\Support\Carbon::parse($row->receipt_date)->format('d M Y') }}</td>
                                <td class="px-4 py-3"><span class="{{ $sourceClass }}">{{ $sources[$row->source] ?? $row->source }}</span></td>
                                <td class="px-4 py-3">{{ $row->supplier_name ?: $row->vendor_name ?: '-' }}</td>
                                <td class="px-4 py-3">{{ $row->outlet_name }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-900">{{ $row->item_name }}</p>
                                    <p class="text-xs text-gray-500">{{ $row->canonical_sku }}</p>
                                </td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $row->qty_received, 4, ',', '.') }} {{ $row->unit }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format((float) $row->unit_price, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format((float) $row->total_value, 0, ',', '.') }}</td>
                                <td class="px-4 py-3"><span class="badge-posted">{{ $row->status }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-10 text-center text-gray-500">Belum ada penerimaan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-sf.card>
    </div>

    {{ $receivings->links() }}
</div>
@endsection
