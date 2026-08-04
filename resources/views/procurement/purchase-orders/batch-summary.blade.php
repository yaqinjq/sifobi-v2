@extends('layouts.app')

@section('title', 'PO Berhasil Dibuat')

@section('topbar')
<x-sf.page-header
    title="PO Berhasil Dibuat"
    subtitle="{{ $pos->first()?->outlet?->name }}"
    back="{{ route('procurement.purchase-orders.index') }}"
/>
@endsection

@section('content')
<div class="px-4 pt-4 pb-28 lg:px-6 lg:pb-8 max-w-3xl mx-auto w-full space-y-4"
     x-data="{ copied: false }">

    {{-- Success banner --}}
    <div class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 flex items-start gap-3">
        <svg class="w-5 h-5 text-green-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <div>
            <p class="text-sm font-semibold text-green-800">{{ $pos->count() }} PO berhasil dibuat!</p>
            @php $po1 = $pos->first(); @endphp
            @if($po1->isPlanOrder())
                <p class="text-xs text-green-600 mt-0.5">
                    Plan order — akan otomatis diajukan ke PIC pada
                    <strong>{{ $po1->needed_at->format('d M Y') }}</strong>.
                </p>
            @else
                <p class="text-xs text-green-600 mt-0.5">Ajukan PO untuk persetujuan PIC.</p>
            @endif
        </div>
    </div>

    {{-- PO List --}}
    @foreach($pos as $po)
        <x-sf.card>
            <x-slot:header>
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-heading font-bold text-gray-900 text-sm">{{ $po->po_number }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $po->typeLabel() }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="{{ $po->statusBadgeClass() }}">{{ $po->statusLabel() }}</span>
                        <a href="{{ route('procurement.purchase-orders.show', $po) }}"
                           class="text-xs text-primary-600 font-semibold underline">
                            Detail
                        </a>
                    </div>
                </div>
            </x-slot:header>

            <div class="divide-y divide-gray-50">
                @foreach($po->items as $item)
                    <div class="flex items-center gap-3 px-4 py-2.5">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 truncate">{{ $item->item?->name ?? '—' }}</p>
                        </div>
                        <span class="text-sm text-gray-600 shrink-0 font-semibold">
                            {{ rtrim(rtrim((string)$item->qty_ordered, '0'), '.') }}
                            {{ $item->unit?->code ?? '' }}
                        </span>
                    </div>
                @endforeach
            </div>

            @if(!$po->isPlanOrder())
                <div class="px-4 pb-3 pt-2">
                    <form method="POST" action="{{ route('procurement.purchase-orders.submit', $po) }}">
                        @csrf
                        <button type="submit" class="sf-btn-primary w-full text-sm">
                            Ajukan ke PIC untuk Persetujuan
                        </button>
                    </form>
                </div>
            @endif
        </x-sf.card>
    @endforeach

    {{-- WhatsApp Copy Section --}}
    <x-sf.card title="Kirim ke WhatsApp Group">
        <div class="px-4 pb-4">
            <p class="text-xs text-gray-500 mb-3">Salin teks di bawah lalu kirim ke group WhatsApp tim Anda:</p>

            @php
                $waText  = "*PURCHASE ORDER - " . strtoupper(config('app.name', 'SIFOBI')) . "*\n";
                $waText .= "Outlet: " . ($pos->first()?->outlet?->name ?? '—') . "\n";
                $waText .= "Dept: " . ($pos->first()?->department?->name ?? '—') . "\n";
                $waText .= "Tanggal: " . now()->format('d M Y') . "\n";
                $waText .= "\n";
                foreach ($pos as $po) {
                    $waText .= "*PO " . $po->typeLabel() . ":* " . $po->po_number . "\n";
                    foreach ($po->items as $idx => $item) {
                        $qty = rtrim(rtrim((string)$item->qty_ordered, '0'), '.');
                        $waText .= ($idx + 1) . ". " . ($item->item?->name ?? '—') . " — " . $qty . " " . ($item->unit?->code ?? '') . "\n";
                    }
                    $waText .= "\n";
                }
                $waText = trim($waText);
            @endphp

            <textarea id="wa-text" readonly rows="12"
                      class="sf-input text-xs font-mono resize-none w-full bg-gray-50"
                      onclick="this.select()">{{ $waText }}</textarea>

            <button type="button"
                    @click="
                        navigator.clipboard.writeText(document.getElementById('wa-text').value)
                            .then(() => { copied = true; setTimeout(() => copied = false, 2500); });
                    "
                    class="mt-3 w-full rounded-xl border-2 border-green-500 text-green-700 font-semibold py-2.5 text-sm flex items-center justify-center gap-2 transition-colors"
                    :class="copied ? 'bg-green-50' : 'bg-white hover:bg-green-50'">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                    <path d="M12 0C5.373 0 0 5.373 0 12c0 2.123.554 4.118 1.528 5.843L.057 23.143a.5.5 0 00.6.6l5.358-1.475A11.944 11.944 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.886 0-3.655-.518-5.168-1.421l-.371-.22-3.834 1.055 1.027-3.742-.24-.386A9.946 9.946 0 012 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/>
                </svg>
                <span x-text="copied ? 'Tersalin!' : 'Salin untuk WhatsApp'"></span>
            </button>
        </div>
    </x-sf.card>

    {{-- Link ke index --}}
    <div class="text-center">
        <a href="{{ route('procurement.purchase-orders.index') }}"
           class="text-sm text-gray-500 underline">
            Lihat semua PO saya
        </a>
    </div>

</div>
@endsection
