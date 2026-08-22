@extends('layouts.app')

@section('title', 'Shift Kasir')

@section('content')
<x-sf.page-header
    title="Shift Kasir"
    subtitle="Buka/tutup shift & rekonsiliasi kas"
    back="{{ route('pos.orders.index', ['outlet_id' => $outletId]) }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-2xl mx-auto w-full space-y-5">
    <form method="GET" class="flex items-center gap-2">
        <select name="outlet_id" class="sf-input text-sm" onchange="this.form.submit()">
            @foreach($outlets as $o)
                <option value="{{ $o->id }}" @selected($o->id == $outletId)>{{ $o->name }}</option>
            @endforeach
        </select>
    </form>

    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    @if($currentShift)
        <x-sf.card title="Shift Berjalan">
            <div class="space-y-1.5 text-sm mb-4">
                <div class="flex justify-between text-gray-600">
                    <span>Dibuka oleh</span>
                    <span class="text-gray-900">{{ $currentShift->openedBy->name ?? '-' }}</span>
                </div>
                <div class="flex justify-between text-gray-600">
                    <span>Dibuka jam</span>
                    <span class="text-gray-900">{{ $currentShift->opened_at?->format('d/m/Y H:i') }}</span>
                </div>
                <div class="flex justify-between text-gray-600">
                    <span>Kas Awal</span>
                    <span class="text-gray-900">Rp {{ number_format((float) $currentShift->opening_cash, 0, ',', '.') }}</span>
                </div>
            </div>

            <a href="{{ route('pos.shifts.show', $currentShift) }}" class="sf-btn-secondary w-full min-h-11 mb-4 flex items-center justify-center">
                Lihat Detail & Riwayat Pembayaran
            </a>

            <form method="POST" action="{{ route('pos.shifts.close', $currentShift) }}" class="space-y-3 pt-4 border-t border-gray-100">
                @csrf
                <x-sf.form-group label="Hitungan Kas Fisik Saat Ini (Rp)" for="closing_cash_actual" :required="true">
                    <input type="number" id="closing_cash_actual" name="closing_cash_actual" min="0" step="1" class="sf-input text-base" required>
                </x-sf.form-group>
                <x-sf.form-group label="Catatan (opsional)" for="notes">
                    <textarea id="notes" name="notes" rows="2" class="sf-input text-base" maxlength="500"></textarea>
                </x-sf.form-group>
                <button type="submit" class="sf-btn-danger w-full min-h-12">Tutup Shift</button>
            </form>
        </x-sf.card>
    @else
        <x-sf.card title="Buka Shift Baru">
            <form method="POST" action="{{ route('pos.shifts.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="outlet_id" value="{{ $outletId }}">
                <x-sf.form-group label="Kas Awal (Rp)" for="opening_cash" :required="true">
                    <input type="number" id="opening_cash" name="opening_cash" min="0" step="1" value="0" class="sf-input text-base" required>
                </x-sf.form-group>
                <x-sf.form-group label="Catatan (opsional)" for="notes">
                    <textarea id="notes" name="notes" rows="2" class="sf-input text-base" maxlength="500"></textarea>
                </x-sf.form-group>
                <button type="submit" class="sf-btn-primary w-full min-h-12">Buka Shift</button>
            </form>
        </x-sf.card>
    @endif

    <x-sf.card title="Riwayat Shift">
        @if($history->isEmpty())
            <p class="text-sm text-gray-400 py-4 text-center">Belum ada riwayat shift.</p>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($history as $shift)
                    <a href="{{ route('pos.shifts.show', $shift) }}" class="flex items-center justify-between gap-3 px-3 py-3 hover:bg-gray-50 transition-colors">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 truncate">{{ $shift->opened_at?->format('d/m/Y H:i') }}</p>
                            <p class="text-xs text-gray-500">{{ $shift->openedBy->name ?? '-' }}</p>
                        </div>
                        <span class="{{ $shift->statusBadgeClass() }} shrink-0">{{ $shift->statusLabel() }}</span>
                    </a>
                @endforeach
            </div>
            <div class="mt-3">{{ $history->links() }}</div>
        @endif
    </x-sf.card>
</div>
@endsection
