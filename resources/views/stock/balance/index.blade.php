@extends('layouts.app')

@section('title', 'Stok Gudang')

@section('content')
<x-sf.page-header title="Stok Gudang" subtitle="{{ $selectedOutlet?->name ?? 'Semua outlet' }}">
    <x-slot:actions>
        @can('view_reports')
            <x-icon-btn
                icon="history"
                label="Riwayat Mutasi"
                color="gray"
                href="{{ route('laporan.mutasi', request()->only(['outlet_id', 'stock_target'])) }}"
            />
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
            @if($canChangeOutlet)
                <select name="outlet_id" class="sf-input text-base min-h-11">
                    <option value="">Semua outlet</option>
                    @foreach($outlets as $outlet)
                        <option value="{{ $outlet->id }}" @selected((int) $outletId === (int) $outlet->id)>{{ $outlet->name }}</option>
                    @endforeach
                </select>
            @else
                <div class="flex items-center min-h-11 px-3 rounded-xl border border-gray-200 bg-gray-50 text-sm font-semibold text-gray-700">
                    <i class="ti ti-building-store mr-2 text-gray-400" aria-hidden="true"></i>
                    {{ $selectedOutlet?->name ?? auth()->user()->outlet?->name ?? 'Outlet Anda' }}
                </div>
            @endif
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
                $hasReorderConfig = $balance->reorder_point !== null;
                $needsOrder = $hasReorderConfig && (float) $balance->qty_on_hand <= (float) $balance->reorder_point;
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
                    <div class="flex items-center justify-between gap-3 border-t border-gray-100 pt-2">
                        <div class="space-y-1">
                            @if($needsOrder)
                                <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-1 text-xs font-semibold text-red-600">
                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    Perlu Order
                                </span>
                            @elseif($hasReorderConfig)
                                <span class="badge-active">Stok OK</span>
                            @else
                                <span class="badge-draft">Belum Config</span>
                            @endif
                            <p class="text-xs text-gray-500">Update: {{ optional($balance->last_mutation_at ?? $balance->updated_at)->diffForHumans() ?? '-' }}</p>
                        </div>
                        <x-icon-btn
                            icon="history"
                            label="Riwayat"
                            color="gray"
                            href="{{ route('stock.balance.show', ['item' => $item, 'outlet_id' => $balance->outlet_id, 'stock_target' => $balance->stock_target]) }}"
                        />
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
                            <th class="px-4 py-3">Status Order</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        @forelse($balances as $balance)
                            @php
                                $item = $balance->item;
                                $targetClass = $balance->stock_target === 'OUTLET_WAREHOUSE' ? 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-blue-100 text-blue-800' : 'badge-active';
                                $hasReorderConfig = $balance->reorder_point !== null;
                                $needsOrder = $hasReorderConfig && (float) $balance->qty_on_hand <= (float) $balance->reorder_point;
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
                                <td class="px-4 py-3">
                                    @if($needsOrder)
                                        <span class="badge-rejected">Reorder</span>
                                    @elseif($hasReorderConfig)
                                        <span class="badge-active">OK</span>
                                    @else
                                        <span class="badge-draft">Belum Config</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <x-icon-btn
                                        icon="history"
                                        label="Riwayat"
                                        color="gray"
                                        size="sm"
                                        href="{{ route('stock.balance.show', ['item' => $item, 'outlet_id' => $balance->outlet_id, 'stock_target' => $balance->stock_target]) }}"
                                    />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="px-4 py-12 text-center">
                                    <i class="ti ti-database-off text-4xl text-gray-300 mb-3 block" aria-hidden="true"></i>
                                    <p class="font-medium text-gray-600">Belum ada data stok</p>
                                    <p class="text-sm text-gray-400 mt-1">
                                        @if($outletId)
                                            Outlet ini belum memiliki stok. Lakukan Open Stock terlebih dahulu.
                                        @else
                                            Belum ada stok di sistem. Lakukan Open Stock terlebih dahulu.
                                        @endif
                                    </p>
                                </td>
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
