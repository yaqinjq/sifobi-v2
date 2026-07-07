@extends('layouts.app')

@section('title', 'Detail Spoil')

@section('content')
<x-sf.page-header title="Detail Spoil" subtitle="{{ $spoil->item?->name ?? '-' }}" back="{{ route('operations.spoil-wastes.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-4xl mx-auto w-full space-y-4">
    <x-sf.card>
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <span class="{{ $spoil->status_badge_class }}">{{ $spoil->status }}</span>
            @if($spoil->is_duplicate_photo)
                <span class="badge-rejected">Foto duplikat</span>
            @endif
            <span class="text-sm text-gray-500">{{ optional($spoil->recorded_at)->translatedFormat('l, d M Y H:i') }}</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-gray-500">Item</p>
                <p class="font-semibold text-gray-900">{{ $spoil->item?->name ?? '-' }}</p>
                <p class="text-xs text-gray-500">{{ $spoil->item?->canonical_sku ?? '-' }}</p>
            </div>
            <div>
                <p class="text-gray-500">Departemen</p>
                <p class="font-semibold text-gray-900">{{ $spoil->department?->name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-gray-500">Qty</p>
                <p class="font-semibold text-gray-900">{{ $spoil->qty }} {{ $spoil->unit?->abbreviation }}</p>
                <p class="text-xs text-gray-500">{{ $spoil->qty_in_base_unit }} {{ $spoil->item?->baseUnit?->abbreviation }}</p>
            </div>
            <div>
                <p class="text-gray-500">Alasan</p>
                <p class="font-semibold text-gray-900">{{ $spoil->reason_label }}</p>
            </div>
        </div>

        @if($spoil->reason_detail)
            <div class="mt-4 rounded-xl bg-gray-50 p-3 text-sm text-gray-700">
                {{ $spoil->reason_detail }}
            </div>
        @endif
    </x-sf.card>

    <x-sf.card title="Foto Bukti">
        @if($spoil->photo || $spoil->photo_path)
            <a href="{{ asset('storage/'.($spoil->photo ?: $spoil->photo_path)) }}" target="_blank" class="block">
                <img src="{{ asset('storage/'.($spoil->photo ?: $spoil->photo_path)) }}" alt="Foto bukti spoil" class="w-full max-h-[520px] rounded-xl object-cover">
            </a>
        @else
            <p class="text-sm text-gray-500">Tidak ada foto bukti.</p>
        @endif

        @if($spoil->is_duplicate_photo && $spoil->duplicateReference)
            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                Foto ini sama dengan laporan #{{ $spoil->duplicateReference->id }}
                oleh {{ $spoil->duplicateReference->createdBy?->name ?? 'user lain' }}
                pada {{ optional($spoil->duplicateReference->recorded_at)->format('d M Y H:i') }}.
                <a href="{{ route('operations.spoil-wastes.show', $spoil->duplicateReference) }}" class="font-semibold underline">Lihat laporan duplikat</a>
            </div>
        @endif
    </x-sf.card>

    @if($spoil->status === 'PENDING')
        @can('approve_spoil')
            <x-sf.card title="Approval">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <form method="POST" action="{{ route('operations.spoil-wastes.approve', $spoil) }}" class="space-y-3">
                        @csrf
                        <textarea name="approval_notes" rows="3" class="sf-input text-base" placeholder="Catatan approval"></textarea>
                        <button type="submit" class="sf-btn-primary min-h-11 w-full">Approve</button>
                    </form>
                    <form method="POST" action="{{ route('operations.spoil-wastes.reject', $spoil) }}" class="space-y-3">
                        @csrf
                        <textarea name="approval_notes" rows="3" class="sf-input text-base" placeholder="Alasan penolakan" required></textarea>
                        <button type="submit" class="sf-btn-danger min-h-11 w-full">Tolak & Void Stok</button>
                    </form>
                </div>
            </x-sf.card>
        @endcan
    @elseif($spoil->approval_notes)
        <x-sf.card title="Catatan Approval">
            <p class="text-sm text-gray-700">{{ $spoil->approval_notes }}</p>
        </x-sf.card>
    @endif
</div>
@endsection
