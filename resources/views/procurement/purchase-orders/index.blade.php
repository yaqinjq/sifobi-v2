@extends('layouts.app')

@section('title', 'Purchase Order')

@section('content')
<x-sf.page-header title="Purchase Order" subtitle="Kelola pemesanan ke vendor / supplier" />

<div class="px-4 py-5 pb-24 lg:px-6 lg:py-6 max-w-5xl mx-auto w-full">

    {{-- Filter & Tombol Buat --}}
    <div class="flex flex-col sm:flex-row gap-3 mb-5">
        <form method="GET" class="flex flex-wrap gap-2 flex-1">
            <select name="status" class="sf-input text-sm" onchange="this.form.submit()">
                <option value="">Semua Status</option>
                @foreach(\App\Modules\Procurement\Models\PurchaseOrder::STATUS_LABELS as $val => $label)
                    <option value="{{ $val }}" @selected(request('status') === $val)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="type" class="sf-input text-sm" onchange="this.form.submit()">
                <option value="">Semua Tipe</option>
                @foreach(\App\Modules\Procurement\Models\PurchaseOrder::TYPE_LABELS as $val => $label)
                    <option value="{{ $val }}" @selected(request('type') === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
        @can('create_po')
            <a href="{{ route('procurement.purchase-orders.create') }}"
               class="sf-btn-primary text-sm min-h-10 flex items-center gap-2 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Buat PO
            </a>
        @endcan
    </div>

    {{-- List --}}
    <x-sf.card>
        @if($pos->isEmpty())
            <div class="text-center py-12 text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-sm">Belum ada Purchase Order</p>
                @can('create_po')
                    <a href="{{ route('procurement.purchase-orders.create') }}" class="sf-btn-primary mt-4 inline-flex">
                        Buat PO Pertama
                    </a>
                @endcan
            </div>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($pos as $po)
                    <a href="{{ route('procurement.purchase-orders.show', $po) }}"
                       class="flex items-center gap-3 px-4 py-3.5 hover:bg-gray-50 transition-colors">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                                <p class="text-sm font-semibold text-gray-800 font-mono">{{ $po->po_number }}</p>
                                <span class="shrink-0 {{ $po->statusBadgeClass() }} text-xs">{{ $po->statusLabel() }}</span>
                            </div>
                            <p class="text-xs text-gray-500">
                                {{ $po->typeLabel() }}
                                &middot; {{ $po->outlet?->name ?? '—' }}
                                @if($po->department)
                                    &middot; {{ $po->department->name }}
                                @endif
                                &middot; {{ $po->items_count }} item
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $po->created_at->format('d M Y') }}
                                &middot; oleh {{ $po->requestedBy?->name ?? '—' }}
                                @if($po->needed_at)
                                    &middot; dibutuhkan {{ $po->needed_at->format('d M Y') }}
                                @endif
                            </p>
                        </div>
                        <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @endforeach
            </div>

            @if($pos->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $pos->links() }}
                </div>
            @endif
        @endif
    </x-sf.card>
</div>
@endsection
