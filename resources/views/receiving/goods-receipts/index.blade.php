@extends('layouts.app')

@section('title', 'Penerimaan Barang')

@section('content')
<x-sf.page-header title="Penerimaan Barang" subtitle="Terima barang dan posting ke stok gudang outlet">
    <x-slot:actions>
        @can('create_goods_receipt')
            <a href="{{ route('receiving.goods-receipts.create') }}" class="sf-btn-primary min-h-11 px-3">+ Terima</a>
        @endcan
    </x-slot:actions>
</x-sf.page-header>

@php
    $sourceBadgeMap = [
        'OCIA_PO' => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-amber-100 text-amber-800',
        'WIP_CENTRAL_KITCHEN' => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-orange-100 text-orange-800',
        'PURCHASING_DRYGOOD' => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-blue-100 text-blue-800',
        'SUPPLIER_LUAR' => 'inline-flex items-center rounded-full text-xs font-semibold px-2.5 py-0.5 bg-purple-100 text-purple-800',
    ];
@endphp

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full space-y-4">
    <x-sf.card>
        <form method="GET" action="{{ route('receiving.goods-receipts.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <select name="source" class="sf-input text-base min-h-11">
                <option value="">Semua sumber</option>
                @foreach($sources as $value => $label)
                    <option value="{{ $value }}" @selected(request('source') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status" class="sf-input text-base min-h-11">
                <option value="">Semua status</option>
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="sf-input text-base min-h-11">
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="sf-input text-base min-h-11">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari kode/supplier" class="sf-input text-base min-h-11">
            <div class="md:col-span-5 flex gap-2">
                <button type="submit" class="sf-btn-primary min-h-11 px-4">Filter</button>
                <a href="{{ route('receiving.goods-receipts.index') }}" class="sf-btn-secondary min-h-11 px-4">Reset</a>
            </div>
        </form>
    </x-sf.card>

    <div class="lg:hidden space-y-3">
        @forelse($receipts as $receipt)
            <x-sf.card>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-2">
                        <div class="flex flex-wrap gap-2">
                            <span class="{{ $sourceBadgeMap[$receipt->source] ?? 'badge-draft' }}">{{ $receipt->source_label }}</span>
                            <span class="{{ $receipt->status_badge_class }}">{{ $receipt->status }}</span>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-900">{{ $receipt->code ?? $receipt->receipt_number }}</p>
                            <p class="text-sm text-gray-500 truncate">{{ $receipt->supplier_name ?: $receipt->source_label }}</p>
                        </div>
                        <div class="text-xs text-gray-500 space-y-1">
                            <p>{{ optional($receipt->receipt_date)->format('d M Y') }} | {{ $receipt->outlet?->name ?? '-' }}</p>
                            <p>{{ $receipt->items_count }} item | Rp {{ number_format((float) ($receipt->total_value_sum ?? 0), 0, ',', '.') }}</p>
                        </div>
                    </div>
                    <x-icon-btn
                        icon="view"
                        label="Detail"
                        color="gray"
                        href="{{ route('receiving.goods-receipts.show', $receipt) }}"
                    />
                </div>
            </x-sf.card>
        @empty
            <x-sf.card>
                <div class="text-center py-8">
                    <p class="text-sm font-semibold text-gray-900">Belum ada penerimaan.</p>
                    <p class="text-sm text-gray-500 mt-1">Mulai dari tombol Terima Barang.</p>
                </div>
            </x-sf.card>
        @endforelse
    </div>

    <div class="hidden lg:block">
    <x-sf.card padding="false">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3">No</th>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Sumber</th>
                        <th class="px-4 py-3">Supplier/Ref</th>
                        <th class="px-4 py-3">Outlet</th>
                        <th class="px-4 py-3 text-right">Items</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @forelse($receipts as $receipt)
                        <tr class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }}">
                            <td class="px-4 py-3 text-gray-500">{{ $receipts->firstItem() + $loop->index }}</td>
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $receipt->code ?? $receipt->receipt_number }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ optional($receipt->receipt_date)->format('d M Y') }}</td>
                            <td class="px-4 py-3"><span class="{{ $sourceBadgeMap[$receipt->source] ?? 'badge-draft' }}">{{ $receipt->source_label }}</span></td>
                            <td class="px-4 py-3 text-gray-600">{{ $receipt->supplier_name ?: ($receipt->external_po_number ?: '-') }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $receipt->outlet?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-right text-gray-600">{{ $receipt->items_count }}</td>
                            <td class="px-4 py-3 text-right text-gray-900">Rp {{ number_format((float) ($receipt->total_value_sum ?? 0), 0, ',', '.') }}</td>
                            <td class="px-4 py-3"><span class="{{ $receipt->status_badge_class }}">{{ $receipt->status }}</span></td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <x-icon-btn
                                        icon="view"
                                        label="Detail"
                                        color="gray"
                                        size="sm"
                                        href="{{ route('receiving.goods-receipts.show', $receipt) }}"
                                    />
                                    @if($receipt->status === 'DRAFT')
                                        @can('create_goods_receipt')
                                            <x-icon-btn
                                                icon="edit"
                                                label="Edit"
                                                color="blue"
                                                size="sm"
                                                href="{{ route('receiving.goods-receipts.edit', $receipt) }}"
                                            />
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-10 text-center text-sm text-gray-500">Belum ada penerimaan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-sf.card>
    </div>

    {{ $receipts->links() }}
</div>
@endsection
