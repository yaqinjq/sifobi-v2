@extends('layouts.app')

@section('title', 'Detail Shift Kasir')

@section('content')
<x-sf.page-header
    title="Detail Shift Kasir"
    subtitle="{{ $shift->opened_at?->format('d/m/Y H:i') }}"
    back="{{ route('pos.shifts.index', ['outlet_id' => $shift->outlet_id]) }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-2xl mx-auto w-full space-y-5">
    <div>
        <span class="{{ $shift->statusBadgeClass() }}">{{ $shift->statusLabel() }}</span>
    </div>

    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <x-sf.card title="Ringkasan Kas">
        <div class="space-y-1.5 text-sm">
            <div class="flex justify-between text-gray-600">
                <span>Dibuka oleh</span>
                <span class="text-gray-900">{{ $shift->openedBy->name ?? '-' }}</span>
            </div>
            <div class="flex justify-between text-gray-600">
                <span>Kas Awal</span>
                <span class="text-gray-900">Rp {{ number_format((float) $shift->opening_cash, 0, ',', '.') }}</span>
            </div>
            @if($shift->closed_at)
                <div class="flex justify-between text-gray-600">
                    <span>Ditutup oleh</span>
                    <span class="text-gray-900">{{ $shift->closedBy->name ?? '-' }} &middot; {{ $shift->closed_at->format('d/m/Y H:i') }}</span>
                </div>
                <div class="flex justify-between text-gray-600">
                    <span>Kas Diharapkan (Sistem)</span>
                    <span class="text-gray-900">Rp {{ number_format((float) $shift->closing_cash_expected, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between text-gray-600">
                    <span>Kas Fisik (Dihitung)</span>
                    <span class="text-gray-900">Rp {{ number_format((float) $shift->closing_cash_actual, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between font-bold text-base pt-1.5 border-t border-gray-100">
                    <span>Selisih</span>
                    <span class="{{ $shift->varianceBadgeClass() }}">Rp {{ number_format((float) $shift->variance, 0, ',', '.') }}</span>
                </div>
            @endif
            @if($shift->reconciled_at)
                <div class="flex justify-between text-gray-600 pt-1.5">
                    <span>Direkonsiliasi oleh</span>
                    <span class="text-gray-900">{{ $shift->reconciledBy->name ?? '-' }} &middot; {{ $shift->reconciled_at->format('d/m/Y H:i') }}</span>
                </div>
            @endif
            @if($shift->notes)
                <p class="text-xs text-gray-500 pt-2 border-t border-gray-100">{{ $shift->notes }}</p>
            @endif
        </div>
    </x-sf.card>

    @can('approve_pos_shift')
        @if($shift->canReconcile())
            <x-sf.card title="Rekonsiliasi">
                <form method="POST" action="{{ route('pos.shifts.reconcile', $shift) }}" class="space-y-3">
                    @csrf
                    <x-sf.form-group label="Catatan Manager (opsional)" for="notes">
                        <textarea id="notes" name="notes" rows="2" class="sf-input text-base" maxlength="500"></textarea>
                    </x-sf.form-group>
                    <button type="submit" class="sf-btn-primary w-full min-h-12">Konfirmasi Rekonsiliasi</button>
                </form>
            </x-sf.card>
        @endif
    @endcan

    <x-sf.card title="Pembayaran Selama Shift">
        @if($shift->payments->isEmpty())
            <p class="text-sm text-gray-400 py-4 text-center">Belum ada pembayaran.</p>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($shift->payments as $payment)
                    <div class="flex items-center justify-between gap-3 py-2 text-sm">
                        <div class="min-w-0">
                            <span class="text-gray-900">{{ $payment->order->order_number ?? '-' }}</span>
                            <span class="text-gray-500"> &middot; {{ $payment->methodLabel() }}</span>
                        </div>
                        <span class="font-semibold text-gray-900 shrink-0">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-sf.card>
</div>
@endsection
