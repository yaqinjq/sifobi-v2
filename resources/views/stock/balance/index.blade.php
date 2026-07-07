@extends('layouts.app')

@section('title', 'Stok Gudang')

@section('content')
<x-sf.page-header title="Stok Gudang" subtitle="{{ $selectedOutlet?->name ?? 'Semua outlet' }}">
    <x-slot:actions>
        @can('view_reports')
            <a href="{{ route('laporan.mutasi', request()->only(['outlet_id', 'stock_target'])) }}" class="sf-btn-secondary min-h-11 px-3 text-xs">
                Riwayat
            </a>
        @endcan
    </x-slot:actions>
</x-sf.page-header>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-7xl mx-auto w-full space-y-4">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <x-sf.stat label="Total Item" :value="number_format((int) ($summary->total_items ?? 0))" />
        <x-sf.stat label="Item Kosong" :value="number_format((int) ($summary->empty_items ?? 0))" />
        <x-sf.stat label="Nilai Total" :value="'Rp '.number_format((float) ($summary->total_inventory_value ?? 0), 0, ',', '.')" />
    </div>

    <x-sf.card>
        <form method="GET" action="{{ route('stock.balance.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <select name="outlet_id" class="sf-input text-base min-h-11">
                <option value="">Semua outlet</option>
                @foreach($outlets as $outlet)
                    <option value="{{ $outlet->id }}" @selected((int) $outletId === (int) $outlet->id)>{{ $outlet->name }}</option>
                @endforeach
            </select>
            <select name="stock_target" class="sf-input text-base min-h-11">
                <option value="">Semua target</option>
                @foreach($stockTargets as $value => $label)
                    <option value="{{ $value }}" @selected($stockTarget === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="category_id" class="sf-input text-base min-h-11">
                <option value="">Semua kategori</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) $categoryId === (int) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ $search }}" placeholder="Cari item atau SKU" class="sf-input text-base min-h-11">
            <div class="flex items-center gap-3">
                <label class="flex min-h-11 items-center gap-2 rounded-xl border border-gray-200 px-3 text-sm text-gray-700">
                    <input type="checkbox" name="show_empty" value="1" @checked($showEmpty) class="rounded border-gray-300 text-primary-700">
                    Stok 0
                </label>
                <button type="submit" class="sf-btn-primary min-h-11 px-4 flex-1">Filter</button>
            </div>
        </form>
    </x-sf.card>

    <div class="lg:hidden space-y-3">
        @forelse($balances as $balance)
            @php
                $item = $balance->item;
                $categoryClass = 'badge-draft';
                $targetClass = $balance->stock_target === 'OUTLET_WAREHOUSE' ? 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-blue-100 text-blue-800' : 'badge-active';
                $qtyClass = (float) $balance->qty_on_hand <= 0 ? 'text-red-600 bg-red-50' : 'text-gray-900 bg-gray-50';
            @endphp
            <x-sf.card>
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <span class="{{ $categoryClass }}">{{ $item?->category?->name ?? 'Tanpa Kategori' }}</span>
                        <span class="{{ $targetClass }}">{{ $stockTargets[$balance->stock_target] ?? $balance->stock_target }}</span>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">{{ $item?->name ?? '-' }}</p>
                        <p class="text-xs text-gray-500 mt-1">SKU: {{ $item?->canonical_sku ?? '-' }}</p>
                    </div>
                    <div class="rounded-xl {{ $qtyClass }} p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Stok Saat Ini</p>
                        <p class="mt-1 text-xl font-bold">
                            {{ number_format($balance->qty_whole, 0, ',', '.') }}
                            {{ $item?->inventoryUnit?->abbreviation ?? $item?->baseUnit?->abbreviation ?? 'unit' }}
                            {{ number_format($balance->qty_loose, 4, ',', '.') }}
                            {{ $item?->baseUnit?->abbreviation ?? 'base' }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">Base: {{ number_format((float) $balance->qty_on_hand, 4, ',', '.') }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-xs text-gray-500">HPP/unit</p>
                            <p class="font-semibold text-gray-900">Rp {{ number_format((float) $balance->avg_cost, 2, ',', '.') }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Nilai</p>
                            <p class="font-semibold text-gray-900">Rp {{ number_format((float) $balance->total_value, 0, ',', '.') }}</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs text-gray-500">Update: {{ optional($balance->last_mutation_at ?? $balance->updated_at)->diffForHumans() ?? '-' }}</p>
                        <a href="{{ route('stock.balance.show', ['item' => $item, 'outlet_id' => $balance->outlet_id, 'stock_target' => $balance->stock_target]) }}" class="sf-btn-secondary min-h-11 px-4">Riwayat</a>
                    </div>
                </div>
            </x-sf.card>
        @empty
            <x-sf.empty-state title="Stok belum ada" description="Balance akan muncul setelah Open Stock, penerimaan, spoil, atau opname diposting." />
        @endforelse
    </div>

    <div class="hidden lg:block">
        <x-sf.card padding="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3">No</th>
                            <th class="px-4 py-3">SKU</th>
                            <th class="px-4 py-3">Nama Item</th>
                            <th class="px-4 py-3">Kategori</th>
                            <th class="px-4 py-3">Target</th>
                            <th class="px-4 py-3 text-right">Stok Utuh</th>
                            <th class="px-4 py-3 text-right">Stok Ecer</th>
                            <th class="px-4 py-3">Satuan</th>
                            <th class="px-4 py-3 text-right">HPP</th>
                            <th class="px-4 py-3 text-right">Nilai</th>
                            <th class="px-4 py-3">Update</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        @forelse($balances as $balance)
                            @php
                                $item = $balance->item;
                                $targetClass = $balance->stock_target === 'OUTLET_WAREHOUSE' ? 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-blue-100 text-blue-800' : 'badge-active';
                            @endphp
                            <tr class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }}">
                                <td class="px-4 py-3 text-gray-500">{{ $balances->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $item?->canonical_sku ?? '-' }}</td>
                                <td class="px-4 py-3 font-semibold text-gray-900">{{ $item?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $item?->category?->name ?? '-' }}</td>
                                <td class="px-4 py-3"><span class="{{ $targetClass }}">{{ $stockTargets[$balance->stock_target] ?? $balance->stock_target }}</span></td>
                                <td class="px-4 py-3 text-right">{{ number_format($balance->qty_whole, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($balance->qty_loose, 4, ',', '.') }}</td>
                                <td class="px-4 py-3">{{ $item?->inventoryUnit?->abbreviation ?? $item?->baseUnit?->abbreviation ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format((float) $balance->avg_cost, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">Rp {{ number_format((float) $balance->total_value, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ optional($balance->last_mutation_at ?? $balance->updated_at)->diffForHumans() ?? '-' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('stock.balance.show', ['item' => $item, 'outlet_id' => $balance->outlet_id, 'stock_target' => $balance->stock_target]) }}" class="sf-btn-secondary min-h-9 px-3">Riwayat</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-4 py-10 text-center text-gray-500">Stok belum ada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-sf.card>
    </div>

    <div>
        {{ $balances->links() }}
    </div>

    <p class="text-xs text-gray-500">
        Menampilkan {{ $balances->count() }} item dari {{ $balances->total() }} data. Total nilai: Rp {{ number_format((float) ($summary->total_inventory_value ?? 0), 0, ',', '.') }}.
    </p>
</div>
@endsection
