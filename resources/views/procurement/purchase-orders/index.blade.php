@extends('layouts.app')

@section('title', 'Purchase Order')

@section('content')
<x-sf.page-header title="Purchase Order" subtitle="Kelola pemesanan ke vendor / supplier" />

<div class="px-4 py-5 pb-28 lg:px-6 lg:py-6 max-w-5xl mx-auto w-full">

    {{-- Filter type + Tombol Buat --}}
    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <form method="GET" class="flex gap-2 flex-1">
            <input type="hidden" name="tab" value="{{ $tab }}">
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

    {{-- Tabs --}}
    @php
        $tabs = [
            'all'       => ['label' => 'Semua',                 'status' => null],
            'draft'     => ['label' => 'Draft',                 'status' => 'DRAFT'],
            'submitted' => ['label' => 'Diajukan',              'status' => 'SUBMITTED'],
            'approved'  => ['label' => 'Disetujui',             'status' => 'APPROVED'],
            'sent'      => ['label' => 'Terkirim ke Vendor',    'status' => 'SENT'],
            'shipped'   => ['label' => 'Dikirim Vendor',        'status' => 'SHIPPED'],
            'closed'    => ['label' => 'Selesai',               'status' => 'CLOSED'],
            'rejected'  => ['label' => 'Ditolak',               'status' => 'REJECTED'],
        ];
        $totalAll = $counts->sum();
    @endphp

    <div class="overflow-x-auto -mx-4 px-4 mb-4">
        <div class="flex gap-0 border-b border-gray-200 min-w-max">
            @foreach($tabs as $key => $tabInfo)
                @php
                    $isActive = $tab === $key;
                    $cnt = $key === 'all' ? $totalAll : ($counts[$tabInfo['status']] ?? 0);
                    $url = request()->fullUrlWithQuery(['tab' => $key, 'page' => null]);
                @endphp
                <a href="{{ $url }}"
                   class="flex items-center gap-1.5 px-3 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition-colors
                          {{ $isActive
                             ? 'border-green-700 text-green-800'
                             : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    {{ $tabInfo['label'] }}
                    @if($cnt > 0)
                        <span class="text-xs rounded-full px-1.5 py-0.5 font-semibold
                              {{ $isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">
                            {{ $cnt }}
                        </span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>

    {{-- List --}}
    <x-sf.card>
        @if($pos->isEmpty())
            <div class="text-center py-12 text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-sm">Tidak ada PO pada tab ini</p>
                @if($tab === 'all' || $tab === 'draft')
                    @can('create_po')
                        <a href="{{ route('procurement.purchase-orders.create') }}" class="sf-btn-primary mt-4 inline-flex">
                            Buat PO Pertama
                        </a>
                    @endcan
                @endif
            </div>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($pos as $po)
                    <a href="{{ route('procurement.purchase-orders.show', $po) }}"
                       class="flex items-start gap-3 px-4 py-3.5 hover:bg-gray-50 transition-colors">

                        {{-- Icon status kiri --}}
                        <div class="mt-0.5 shrink-0">
                            @if($po->status === 'SHIPPED')
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                                    </svg>
                                </div>
                            @elseif($po->status === 'SENT')
                                <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                    </svg>
                                </div>
                            @elseif($po->status === 'SUBMITTED')
                                <div class="w-8 h-8 rounded-full bg-yellow-100 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-yellow-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                            @elseif($po->status === 'APPROVED')
                                <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                            @elseif($po->status === 'CLOSED')
                                <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                            @elseif($po->status === 'REJECTED')
                                <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </div>
                            @else
                                <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                            @endif
                        </div>

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
                        <svg class="w-4 h-4 text-gray-300 shrink-0 mt-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
