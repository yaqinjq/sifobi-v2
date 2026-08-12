@extends('layouts.app')

@section('title', 'Katalog Wipro')

@section('content')
<x-sf.page-header
    title="Katalog Wipro"
    subtitle="Import produk Wipro untuk digunakan saat PO ke Central Kitchen"
    back="{{ route('settings.index') }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-3xl mx-auto w-full space-y-5">

    @if(session('success'))
        <div class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 flex items-start gap-3">
            <svg class="w-5 h-5 text-green-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <p class="text-sm text-green-800">{{ session('success') }}</p>
        </div>
    @endif

    @if(session('warning'))
        <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            <p class="text-sm text-amber-800">{{ session('warning') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 flex items-start gap-3">
            <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-red-800">{{ session('error') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3">
            <p class="text-sm text-red-800">{{ $errors->first() }}</p>
        </div>
    @endif

    {{-- Statistik katalog saat ini --}}
    <x-sf.card title="Status Katalog">
        <div class="px-4 pb-4 grid grid-cols-3 gap-3">
            <div class="text-center rounded-xl bg-primary-50 py-4 px-2">
                <p class="text-2xl font-heading font-bold text-primary-700">{{ $stats['total'] }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Total Item</p>
            </div>
            <div class="text-center rounded-xl bg-primary-50 py-4 px-2">
                <p class="text-2xl font-heading font-bold text-primary-700">{{ $stats['categories'] }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Kategori</p>
            </div>
            <div class="text-center rounded-xl bg-gray-50 py-4 px-2">
                <p class="text-xs font-semibold text-gray-700 mt-1">
                    {{ $stats['last_import'] ? \Carbon\Carbon::parse($stats['last_import'])->format('d M Y') : '—' }}
                </p>
                <p class="text-xs text-gray-500 mt-0.5">Terakhir Diperbarui</p>
            </div>
        </div>
    </x-sf.card>

    {{-- Form upload --}}
    <x-sf.card title="Upload Katalog Baru">
        <form method="POST"
              action="{{ route('settings.wipro-catalog.import') }}"
              enctype="multipart/form-data"
              x-data="{ file: null, uploading: false }"
              @submit="uploading = true"
              class="px-4 pb-5 space-y-4">
            @csrf

            <div class="rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 p-6 text-center">
                <svg class="w-10 h-10 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-sm font-medium text-gray-700 mb-1">Pilih file katalog Wipro</p>
                <p class="text-xs text-gray-500 mb-3">Format Excel (.xlsx / .xls) — setiap sheet = satu kategori</p>
                <label class="cursor-pointer">
                    <span class="sf-btn-secondary text-sm px-4 py-2">
                        Pilih File
                    </span>
                    <input type="file" name="catalog_file" accept=".xlsx,.xls"
                           class="hidden"
                           @change="file = $event.target.files[0]?.name ?? null">
                </label>
                <p class="text-xs text-primary-600 mt-2 font-semibold" x-text="file ?? ''" x-show="file"></p>
            </div>

            <div class="rounded-xl bg-blue-50 border border-blue-100 px-4 py-3 space-y-1.5">
                <p class="text-xs font-semibold text-blue-800">Format kolom yang diharapkan (row 1 = header):</p>
                <div class="grid grid-cols-4 gap-1 text-xs text-blue-700 font-mono">
                    <span>A: sku</span>
                    <span>B: product_name</span>
                    <span>C: product_type</span>
                    <span>D: default_unit_symbol</span>
                    <span>E: is_active</span>
                    <span>F: kategori</span>
                    <span>G: CK</span>
                    <span>H: outlet</span>
                </div>
                <p class="text-xs text-blue-600">Import akan menambahkan item baru dan memperbarui item yang sudah ada berdasarkan SKU.</p>
            </div>

            <button type="submit"
                    :disabled="!file || uploading"
                    class="w-full sf-btn-primary"
                    :class="(!file || uploading) ? 'opacity-50 cursor-not-allowed' : ''">
                <span x-show="!uploading" class="flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    Import Katalog
                </span>
                <span x-show="uploading" class="flex items-center justify-center gap-2">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    Sedang diproses…
                </span>
            </button>
        </form>
    </x-sf.card>

    <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3">
        <p class="text-xs text-amber-800">
            <strong>Catatan:</strong> Import ini menggantikan katalog yang ada berdasarkan SKU.
            Jika SKU sudah ada, data akan diperbarui. Katalog perlu diperbarui setiap kali Wipro merilis daftar produk baru.
        </p>
    </div>

</div>
@endsection
