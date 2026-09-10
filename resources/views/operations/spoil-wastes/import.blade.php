@extends('layouts.app')

@section('title', 'Import Spoil & Waste')

@section('content')
<x-sf.page-header title="Import Spoil & Waste" subtitle="Upload histori dari Excel" back="{{ route('operations.spoil-wastes.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-3xl mx-auto w-full space-y-4">
    @if(session('success'))
        <div class="rounded-2xl border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ session('warning') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    @if(session('importErrors') && count(session('importErrors')) > 0)
        <x-sf.card title="Baris yang gagal diimpor">
            <div class="space-y-2">
                @foreach(session('importErrors') as $err)
                    <div class="rounded-xl bg-red-50 border border-red-100 px-3 py-2 text-sm text-red-700">
                        <span class="font-semibold">Baris {{ $err['row'] }}:</span> {{ $err['message'] }}
                    </div>
                @endforeach
            </div>
        </x-sf.card>
    @endif

    <x-sf.card title="1. Unduh Template">
        <p class="text-sm text-gray-600 mb-3">Unduh template Excel, isi sesuai contoh dan petunjuk di sheet "PETUNJUK", lalu upload di bawah.</p>
        <a href="{{ route('operations.spoil-wastes.import-template') }}" class="sf-btn-secondary min-h-11 w-full sm:w-auto px-4 inline-flex items-center justify-center">
            Unduh Template
        </a>
    </x-sf.card>

    <x-sf.card title="2. Upload File">
        <form method="POST" action="{{ route('operations.spoil-wastes.import') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="sf-label">File Excel (.xlsx/.xls/.csv, maks 10MB)</label>
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="sf-input text-base min-h-11">
                @error('file')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="rounded-xl bg-blue-50 border border-blue-200 px-3 py-2 text-xs text-blue-800">
                Qty akan langsung memotong stok saat ini. Baris yang berhasil otomatis ber-status Approved.
                Baris yang gagal (item/outlet/satuan tidak ditemukan) dilewati dengan laporan error, baris lain tetap diproses.
            </div>
            <button type="submit" class="sf-btn-primary min-h-11 w-full">Import Sekarang</button>
        </form>
    </x-sf.card>
</div>
@endsection
