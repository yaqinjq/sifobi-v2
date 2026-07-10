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

    @if($suggestion)
        @php
            $daysRemaining = $suggestion['days_remaining'];
            $daysTone = $daysRemaining !== null && $daysRemaining < 3
                ? 'critical'
                : ($daysRemaining !== null && $daysRemaining < 7 ? 'warning' : 'healthy');
            $daysToneMap = [
                'critical' => [
                    'panel' => 'bg-red-50',
                    'text' => 'text-red-700',
                ],
                'warning' => [
                    'panel' => 'bg-amber-50',
                    'text' => 'text-amber-700',
                ],
                'healthy' => [
                    'panel' => 'bg-green-50',
                    'text' => 'text-green-700',
                ],
            ];
            $daysPanelClass = $daysToneMap[$daysTone]['panel'];
            $daysTextClass = $daysToneMap[$daysTone]['text'];
        @endphp

        <x-sf.card title="Analisis Stok & Saran Order">
            <div class="mb-4 flex items-center gap-2 text-primary-800">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.674M12 3a6 6 0 00-3.6 10.8c.75.56 1.263 1.315 1.263 2.2h4.674c0-.885.513-1.64 1.263-2.2A6 6 0 0012 3z"/>
                </svg>
                <span class="text-sm font-semibold">Rekomendasi berdasarkan pola pemakaian dan event</span>
            </div>

            <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="rounded-xl bg-gray-50 p-3 text-center">
                    <p class="mb-1 text-xs text-gray-500">Rata-rata/hari</p>
                    <p class="text-lg font-bold text-gray-900">
                        {{ number_format($suggestion['avg_daily_usage'], 2, ',', '.') }}
                        <span class="text-xs font-normal text-gray-500">{{ $suggestion['unit_abbreviation'] }}</span>
                    </p>
                </div>
                <div class="rounded-xl p-3 text-center {{ $daysPanelClass }}">
                    <p class="mb-1 text-xs text-gray-500">Est. Habis</p>
                    <p class="text-lg font-bold {{ $daysTextClass }}">
                        {{ $daysRemaining === null ? '-' : number_format($daysRemaining, 1, ',', '.').' hari' }}
                    </p>
                </div>
                <div class="rounded-xl bg-gray-50 p-3 text-center">
                    <p class="mb-1 text-xs text-gray-500">Min Stok</p>
                    <p class="text-lg font-bold text-gray-900">
                        {{ number_format($suggestion['min_stock_qty'], 1, ',', '.') }}
                        <span class="text-xs font-normal text-gray-500">{{ $suggestion['unit_abbreviation'] }}</span>
                    </p>
                </div>
                <div class="rounded-xl bg-primary-50 p-3 text-center">
                    <p class="mb-1 text-xs font-semibold text-primary-600">Rekomendasi Order</p>
                    <p class="text-xl font-bold text-primary-800">
                        {{ number_format($suggestion['recommended_order'], 1, ',', '.') }}
                        <span class="text-sm font-normal">{{ $suggestion['unit_abbreviation'] }}</span>
                    </p>
                </div>
            </div>

            @if(!empty($suggestion['upcoming_events']))
                <div class="border-t border-gray-100 pt-4">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Event Mendatang yang Mempengaruhi
                    </p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($suggestion['upcoming_events'] as $event)
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-800">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 2v3m8-3v3M3 9h18M5 4h14a2 2 0 012 2v14H3V6a2 2 0 012-2z"/>
                                </svg>
                                {{ $event['name'] }}
                                <span class="text-amber-600">
                                    {{ $event['demand_change_pct'] > 0 ? '+' : '' }}{{ number_format($event['demand_change_pct'], 0) }}%
                                </span>
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($suggestion['is_critical'])
                <div class="mt-4 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-3">
                    <svg class="h-5 w-5 shrink-0 text-red-600" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-sm font-semibold text-red-800">
                        KRITIS - stok di bawah batas minimum. Segera lakukan order.
                    </p>
                </div>
            @endif
        </x-sf.card>
    @endif

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
