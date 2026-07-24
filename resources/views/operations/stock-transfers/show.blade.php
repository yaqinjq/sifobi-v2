@extends('layouts.app')

@section('title', 'Detail Transfer Stok')

@section('content')
<x-sf.page-header title="Transfer Stok" subtitle="{{ $transfer->fromOutlet?->name }} &rarr; {{ $transfer->toOutlet?->name }}" back="{{ route('operations.stock-transfers.index') }}" />

<div class="px-4 py-5 pb-24 lg:px-6 lg:py-6 max-w-3xl mx-auto w-full" x-data="{ rejectModal: false, voidModal: false }">

    {{-- Status badge --}}
    <div class="flex items-center gap-3 mb-4">
        <span class="{{ $transfer->statusBadgeClass() }} text-sm px-3 py-1">{{ $transfer->statusLabel() }}</span>
        <span class="text-sm text-gray-500">{{ $transfer->transfer_date->format('d M Y') }}</span>
    </div>

    {{-- Detail --}}
    <x-sf.card title="Detail Transfer" class="mb-4">
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <p class="sf-label text-xs mb-0.5">Outlet Asal</p>
                <p class="font-medium text-gray-800">{{ $transfer->fromOutlet?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="sf-label text-xs mb-0.5">Outlet Tujuan</p>
                <p class="font-medium text-gray-800">{{ $transfer->toOutlet?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="sf-label text-xs mb-0.5">Dibuat oleh</p>
                <p class="text-gray-700">{{ $transfer->createdBy?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="sf-label text-xs mb-0.5">Tanggal Transfer</p>
                <p class="text-gray-700">{{ $transfer->transfer_date->format('d M Y') }}</p>
            </div>
            @if($transfer->notes)
            <div class="col-span-2">
                <p class="sf-label text-xs mb-0.5">Catatan</p>
                <p class="text-gray-700">{{ $transfer->notes }}</p>
            </div>
            @endif
            @if($transfer->status === 'REJECTED' && $transfer->rejection_reason)
            <div class="col-span-2">
                <p class="sf-label text-xs mb-0.5">Alasan Penolakan</p>
                <p class="text-red-700 bg-red-50 rounded-lg px-3 py-2">{{ $transfer->rejection_reason }}</p>
            </div>
            @endif
            @if($transfer->status === 'VOIDED' && $transfer->void_reason)
            <div class="col-span-2">
                <p class="sf-label text-xs mb-0.5">Alasan Pembatalan</p>
                <p class="text-orange-700 bg-orange-50 rounded-lg px-3 py-2">{{ $transfer->void_reason }}</p>
            </div>
            @endif
        </div>
    </x-sf.card>

    {{-- Items --}}
    <x-sf.card title="Daftar Item" class="mb-4">
        @forelse($transfer->items as $tItem)
        <div class="flex items-center gap-3 py-3 {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800">{{ $tItem->item?->name ?? '—' }}</p>
                <p class="text-xs text-gray-500">{{ $tItem->item?->inventoryUnit?->name ?? '' }}</p>
            </div>
            <div class="text-right shrink-0">
                <p class="text-sm font-semibold text-gray-800">{{ number_format((float) $tItem->qty, 2) }}</p>
                <p class="text-xs text-gray-400">{{ $tItem->unit?->abbreviation ?? '' }}</p>
            </div>
        </div>
        @empty
        <p class="text-sm text-gray-400 text-center py-4">Tidak ada item</p>
        @endforelse
    </x-sf.card>

    {{-- Aksi --}}
    @if($transfer->status === 'DRAFT')
    <div class="flex gap-3">
        <form method="POST" action="{{ route('operations.stock-transfers.submit', $transfer) }}" class="flex-1">
            @csrf
            <button type="submit" class="sf-btn-primary w-full min-h-11">Submit untuk Approval</button>
        </form>
    </div>
    @endif

    @if($transfer->status === 'SUBMITTED')
    @can('approve_stock_transfers')
    <div class="flex gap-3">
        <form method="POST" action="{{ route('operations.stock-transfers.approve', $transfer) }}" class="flex-1">
            @csrf
            <button type="submit" class="sf-btn-primary w-full min-h-11">Setujui Transfer</button>
        </form>
        <button type="button" @click="rejectModal = true"
                class="sf-btn-secondary min-h-11 px-5">Tolak</button>
    </div>
    @endcan
    @endif

    @if($transfer->status === 'APPROVED')
    @can('approve_stock_transfers')
    <button type="button" @click="voidModal = true"
            class="sf-btn-secondary min-h-10 text-sm text-red-600 border-red-200 hover:bg-red-50">
        Batalkan Transfer
    </button>
    @endcan
    @endif

    {{-- Modal Tolak --}}
    <div x-show="rejectModal"
         x-transition.opacity
         class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/50">
        <div @click.away="rejectModal = false"
             class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
            <h3 class="font-heading font-bold text-gray-900 text-lg mb-4">Tolak Transfer</h3>
            <form method="POST" action="{{ route('operations.stock-transfers.reject', $transfer) }}">
                @csrf
                <x-sf.form-group label="Alasan Penolakan" for="rejection_reason" :required="true">
                    <textarea name="rejection_reason" id="rejection_reason"
                              rows="3"
                              required
                              class="sf-input"
                              placeholder="Masukkan alasan penolakan..."></textarea>
                </x-sf.form-group>
                <div class="flex gap-3 mt-4">
                    <button type="button" @click="rejectModal = false"
                            class="sf-btn-secondary flex-1 min-h-10">Batal</button>
                    <button type="submit"
                            class="sf-btn-danger flex-1 min-h-10">Tolak Transfer</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Void --}}
    <div x-show="voidModal"
         x-transition.opacity
         class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/50">
        <div @click.away="voidModal = false"
             class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
            <h3 class="font-heading font-bold text-gray-900 text-lg mb-1">Batalkan Transfer</h3>
            <p class="text-sm text-gray-500 mb-4">Stok akan dikembalikan ke posisi semula.</p>
            <form method="POST" action="{{ route('operations.stock-transfers.void', $transfer) }}">
                @csrf
                <x-sf.form-group label="Alasan Pembatalan" for="void_reason" :required="true">
                    <textarea name="void_reason" id="void_reason"
                              rows="3"
                              required
                              class="sf-input"
                              placeholder="Masukkan alasan pembatalan..."></textarea>
                </x-sf.form-group>
                <div class="flex gap-3 mt-4">
                    <button type="button" @click="voidModal = false"
                            class="sf-btn-secondary flex-1 min-h-10">Batal</button>
                    <button type="submit"
                            class="sf-btn-danger flex-1 min-h-10">Batalkan Transfer</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
