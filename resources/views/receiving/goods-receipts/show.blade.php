@extends('layouts.app')

@section('title', $receipt->code ?? 'Penerimaan Barang')

@section('content')
<x-sf.page-header title="{{ $receipt->code ?? $receipt->receipt_number }}" subtitle="{{ $receipt->source_label }}" back="{{ route('receiving.goods-receipts.index') }}">
    <x-slot:actions>
        @if(in_array($receipt->status, ['DRAFT', 'REJECTED'], true))
            @can('create_goods_receipt')
                <a href="{{ route('receiving.goods-receipts.edit', $receipt) }}" class="sf-btn-secondary min-h-11 px-3">Edit</a>
            @endcan
        @endif
    </x-slot:actions>
</x-sf.page-header>

@php
    $steps = ['DRAFT', 'SUBMITTED', 'APPROVED', 'POSTED'];
    $activeIndex = array_search($receipt->status, $steps, true);
    $activeIndex = $activeIndex === false ? 0 : $activeIndex;
    $totalValue = $receipt->items->sum(fn ($item) => (float) $item->total_value);
@endphp

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full space-y-4">
    <x-sf.card>
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div class="space-y-3 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="{{ $receipt->source_badge_class }}">{{ $receipt->source_label }}</span>
                    <span class="{{ $receipt->status_badge_class }}">{{ $receipt->status }}</span>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                    <div>
                        <p class="text-gray-500">Outlet</p>
                        <p class="font-semibold text-gray-900">{{ $receipt->outlet?->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Tanggal</p>
                        <p class="font-semibold text-gray-900">{{ optional($receipt->receipt_date)->format('d M Y') }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Supplier/Ref</p>
                        <p class="font-semibold text-gray-900">{{ $receipt->supplier_name ?: ($receipt->external_po_number ?: '-') }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Total</p>
                        <p class="font-semibold text-gray-900">Rp {{ number_format($totalValue, 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            @if($receipt->photo_document)
                <a href="{{ asset('storage/'.$receipt->photo_document) }}" target="_blank" class="block w-full lg:w-44 rounded-xl overflow-hidden border border-gray-100 bg-gray-50">
                    <img src="{{ asset('storage/'.$receipt->photo_document) }}" alt="Dokumen penerimaan" class="w-full h-32 object-cover">
                </a>
            @endif
        </div>
    </x-sf.card>

    <x-sf.card title="Status Workflow">
        <div class="grid grid-cols-4 gap-2">
            @foreach($steps as $index => $step)
                @php
                    $stepClass = $index <= $activeIndex
                        ? 'bg-primary-700 text-white border-primary-700'
                        : 'bg-white text-gray-400 border-gray-200';
                @endphp
                <div class="min-h-11 rounded-xl border px-2 py-3 text-center text-xs font-semibold {{ $stepClass }}">
                    {{ $step }}
                </div>
            @endforeach
        </div>
        @if($receipt->status === 'REJECTED')
            <div class="mt-3 rounded-xl border border-red-100 bg-red-50 p-3 text-sm text-red-800">
                {{ $receipt->review_notes ?: 'Dokumen ditolak.' }}
            </div>
        @endif
    </x-sf.card>

    <x-sf.card title="Item Diterima" subtitle="{{ $receipt->items->count() }} item">
        <div class="lg:hidden space-y-3">
            @foreach($receipt->items as $item)
                @php
                    $diff = (float) $item->qty_received - (float) $item->qty_ordered;
                    $diffClass = $diff < 0 ? 'text-red-600' : ($diff > 0 ? 'text-amber-600' : 'text-green-600');
                @endphp
                <div class="rounded-xl border border-gray-100 p-3">
                    <p class="font-semibold text-gray-900">{{ $item->item?->name ?? '-' }}</p>
                    <p class="text-xs text-gray-500">{{ $item->item?->canonical_sku ?? '-' }}</p>
                    <div class="grid grid-cols-2 gap-2 mt-3 text-sm">
                        <p class="text-gray-500">Qty PO</p>
                        <p class="text-right">{{ $item->qty_ordered }} {{ $item->unit?->abbreviation }}</p>
                        <p class="text-gray-500">Qty Terima</p>
                        <p class="text-right font-semibold">{{ $item->qty_received }} {{ $item->unit?->abbreviation }}</p>
                        <p class="text-gray-500">Selisih</p>
                        <p class="text-right font-semibold {{ $diffClass }}">{{ number_format($diff, 4, ',', '.') }}</p>
                        <p class="text-gray-500">Total</p>
                        <p class="text-right font-semibold">Rp {{ number_format((float) $item->total_value, 0, ',', '.') }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="hidden lg:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3">Satuan</th>
                        <th class="px-4 py-3 text-right">Qty PO</th>
                        <th class="px-4 py-3 text-right">Qty Terima</th>
                        <th class="px-4 py-3 text-right">Selisih</th>
                        <th class="px-4 py-3 text-right">Harga</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3">Exp.Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    @foreach($receipt->items as $item)
                        @php
                            $diff = (float) $item->qty_received - (float) $item->qty_ordered;
                            $diffClass = $diff < 0 ? 'text-red-600' : ($diff > 0 ? 'text-amber-600' : 'text-green-600');
                        @endphp
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-gray-900">{{ $item->item?->name ?? '-' }}</p>
                                <p class="text-xs text-gray-500">{{ $item->item?->canonical_sku ?? '-' }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $item->unit?->abbreviation }}</td>
                            <td class="px-4 py-3 text-right">{{ $item->qty_ordered }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ $item->qty_received }}</td>
                            <td class="px-4 py-3 text-right font-semibold {{ $diffClass }}">{{ number_format($diff, 4, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-semibold">Rp {{ number_format((float) $item->total_value, 0, ',', '.') }}</td>
                            <td class="px-4 py-3">{{ optional($item->expired_date)->format('d M Y') ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50">
                    <tr>
                        <td colspan="6" class="px-4 py-3 text-right font-semibold">Total Nilai</td>
                        <td class="px-4 py-3 text-right font-bold">Rp {{ number_format($totalValue, 0, ',', '.') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-sf.card>

    @if($receipt->status === 'SUBMITTED')
        @canany(['approve_goods_receipt', 'reject_goods_receipt'])
            <x-sf.card title="Review">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                    @can('approve_goods_receipt')
                        <form method="POST" action="{{ route('receiving.goods-receipts.approve', $receipt) }}" class="space-y-3">
                            @csrf
                            <textarea name="review_notes" rows="3" class="sf-input text-base" placeholder="Catatan approval"></textarea>
                            <button type="submit" class="sf-btn-primary min-h-11 w-full">Approve & Post ke Stok</button>
                        </form>
                    @endcan
                    @can('reject_goods_receipt')
                        <form method="POST" action="{{ route('receiving.goods-receipts.reject', $receipt) }}" class="space-y-3">
                            @csrf
                            <textarea name="review_notes" rows="3" class="sf-input text-base" placeholder="Alasan penolakan" required></textarea>
                            <button type="submit" class="sf-btn-danger min-h-11 w-full">Tolak</button>
                        </form>
                    @endcan
                </div>
            </x-sf.card>
        @endcanany
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
        @if($receipt->status === 'DRAFT')
            @can('submit_goods_receipt')
                <form method="POST" action="{{ route('receiving.goods-receipts.submit', $receipt) }}">
                    @csrf
                    <button type="submit" class="sf-btn-primary min-h-11 w-full">Submit Review</button>
                </form>
            @endcan
            @can('create_goods_receipt')
                <form method="POST" action="{{ route('receiving.goods-receipts.destroy', $receipt) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="sf-btn-danger min-h-11 w-full">Hapus Draft</button>
                </form>
            @endcan
        @endif

        @if($receipt->status === 'POSTED')
            <a href="#" class="sf-btn-secondary min-h-11 w-full text-center">Lihat Mutasi Stok</a>
        @endif
    </div>
</div>
@endsection
