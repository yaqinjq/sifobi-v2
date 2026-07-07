@extends('layouts.app')

@section('title', 'Riwayat Stok')

@section('content')
<x-sf.page-header title="{{ $item->name }}" subtitle="SKU: {{ $item->canonical_sku }}" back="{{ route('stock.balance.index', request()->only(['outlet_id', 'stock_target'])) }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-5xl mx-auto w-full space-y-4">
    @php
        $totalQty = $balances->sum(fn ($balance) => (float) $balance->qty_on_hand);
        $totalValue = $balances->sum(fn ($balance) => (float) $balance->total_value);
        $avgCost = $totalQty > 0 ? $totalValue / $totalQty : 0;
    @endphp

    <x-sf.card title="Stok Saat Ini">
        <div class="text-center py-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Total semua target terfilter</p>
            <p class="mt-2 text-3xl font-heading font-bold text-gray-900">
                {{ number_format($totalQty, 4, ',', '.') }} {{ $item->baseUnit?->abbreviation ?? 'base' }}
            </p>
            <p class="mt-2 text-sm text-gray-500">
                HPP rata-rata: Rp {{ number_format($avgCost, 2, ',', '.') }} / {{ $item->baseUnit?->abbreviation ?? 'base' }}
            </p>
            <p class="text-sm text-gray-500">Total nilai: Rp {{ number_format($totalValue, 0, ',', '.') }}</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
            @forelse($balances as $balance)
                @php
                    $targetClass = $balance->stock_target === 'OUTLET_WAREHOUSE' ? 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-blue-100 text-blue-800' : 'badge-active';
                @endphp
                <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <span class="{{ $targetClass }}">{{ $stockTargets[$balance->stock_target] ?? $balance->stock_target }}</span>
                        <span class="text-xs text-gray-500">{{ $balance->outlet?->name ?? '-' }}</span>
                    </div>
                    <p class="mt-3 text-xl font-bold text-gray-900">
                        {{ number_format($balance->qty_whole, 0, ',', '.') }}
                        {{ $item->inventoryUnit?->abbreviation ?? $item->baseUnit?->abbreviation ?? 'unit' }}
                        {{ number_format($balance->qty_loose, 4, ',', '.') }}
                        {{ $item->baseUnit?->abbreviation ?? 'base' }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500">Base: {{ number_format((float) $balance->qty_on_hand, 4, ',', '.') }}</p>
                    <p class="mt-2 text-sm text-gray-600">Nilai: Rp {{ number_format((float) $balance->total_value, 0, ',', '.') }}</p>
                </div>
            @empty
                <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 text-sm text-gray-500">
                    Belum ada saldo untuk item ini.
                </div>
            @endforelse
        </div>
    </x-sf.card>

    <x-sf.card title="Riwayat Mutasi 30 Hari Terakhir" padding="false">
        <div class="lg:hidden divide-y divide-gray-100">
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
                        'TRANSFER_IN' => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-blue-100 text-blue-700',
                        'TRANSFER_OUT' => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-orange-100 text-orange-700',
                    ][$mutation->mutation_type] ?? 'badge-draft';
                @endphp
                <div class="p-4 space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <span class="{{ $badgeClass }}">{{ $mutation->mutation_type }}</span>
                        <span class="text-xs text-gray-500">{{ \Illuminate\Support\Carbon::parse($mutation->performed_at)->format('d M H:i') }}</span>
                    </div>
                    <p class="font-semibold {{ (float) $mutation->qty_change < 0 ? 'text-red-600' : 'text-green-700' }}">
                        {{ (float) $mutation->qty_change > 0 ? '+' : '' }}{{ number_format((float) $mutation->qty_change, 4, ',', '.') }}
                    </p>
                    <p class="text-sm text-gray-500">Balance after: {{ number_format((float) $mutation->balance_after, 4, ',', '.') }}</p>
                    <p class="text-xs text-gray-500">{{ $mutation->notes ?: '-' }}</p>
                </div>
            @empty
                <div class="p-8 text-center text-sm text-gray-500">Belum ada mutasi 30 hari terakhir.</div>
            @endforelse
        </div>

        <div class="hidden lg:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Tipe</th>
                        <th class="px-4 py-3">Target</th>
                        <th class="px-4 py-3 text-right">Qty Change</th>
                        <th class="px-4 py-3 text-right">Qty After</th>
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
                                'TRANSFER_IN' => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-blue-100 text-blue-700',
                                'TRANSFER_OUT' => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-orange-100 text-orange-700',
                            ][$mutation->mutation_type] ?? 'badge-draft';
                        @endphp
                        <tr>
                            <td class="px-4 py-3 text-gray-600">{{ \Illuminate\Support\Carbon::parse($mutation->performed_at)->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3"><span class="{{ $badgeClass }}">{{ $mutation->mutation_type }}</span></td>
                            <td class="px-4 py-3">{{ $stockTargets[$mutation->stock_target] ?? $mutation->stock_target }}</td>
                            <td class="px-4 py-3 text-right font-semibold {{ (float) $mutation->qty_change < 0 ? 'text-red-600' : 'text-green-700' }}">
                                {{ (float) $mutation->qty_change > 0 ? '+' : '' }}{{ number_format((float) $mutation->qty_change, 4, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $mutation->balance_after, 4, ',', '.') }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500">#{{ $mutation->reference_id ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $mutation->notes ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-500">Belum ada mutasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-sf.card>
</div>
@endsection
