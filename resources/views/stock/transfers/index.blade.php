@extends('layouts.app')

@section('title', 'Transfer Stok')

@section('content')
<x-sf.page-header title="Transfer Stok" subtitle="Pindah stok antar outlet" />

<div class="px-4 py-5 pb-24 lg:px-6 lg:py-6 max-w-5xl mx-auto w-full">

    {{-- Filter & Tombol Buat --}}
    <div class="flex flex-col sm:flex-row gap-3 mb-5">
        <form method="GET" class="flex-1 flex gap-2">
            <select name="status" class="sf-input text-sm flex-1" onchange="this.form.submit()">
                <option value="">Semua Status</option>
                @foreach(['DRAFT','SUBMITTED','APPROVED','REJECTED','VOIDED'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst(strtolower($s)) }}</option>
                @endforeach
            </select>
        </form>
        @can('create_stock_transfers')
            <a href="{{ route('stock.transfers.create') }}" class="sf-btn-primary text-sm min-h-10 flex items-center gap-2 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Buat Transfer
            </a>
        @endcan
    </div>

    {{-- Table --}}
    <x-sf.card>
        @if($transfers->isEmpty())
            <div class="text-center py-12 text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
                <p class="text-sm">Belum ada transfer stok</p>
            </div>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($transfers as $transfer)
                <a href="{{ route('stock.transfers.show', $transfer) }}"
                   class="flex items-center gap-3 px-4 py-3.5 hover:bg-gray-50 transition-colors">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <p class="text-sm font-semibold text-gray-800 truncate">
                                {{ $transfer->fromOutlet?->name ?? '—' }}
                                <span class="text-gray-400 font-normal mx-1">&rarr;</span>
                                {{ $transfer->toOutlet?->name ?? '—' }}
                            </p>
                            <span class="shrink-0 {{ $transfer->statusBadgeClass() }} text-xs">{{ $transfer->statusLabel() }}</span>
                        </div>
                        <p class="text-xs text-gray-500">
                            {{ $transfer->transfer_date->format('d M Y') }}
                            &middot; {{ $transfer->items_count }} item
                            &middot; oleh {{ $transfer->createdBy?->name ?? '—' }}
                        </p>
                    </div>
                    <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
                @endforeach
            </div>

            @if($transfers->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $transfers->links() }}
                </div>
            @endif
        @endif
    </x-sf.card>
</div>
@endsection
