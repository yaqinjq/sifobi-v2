@extends('layouts.app')

@section('title', 'Mapping Departemen & Kategori Opname')

@section('content')
<x-sf.page-header title="Mapping Departemen & Kategori Opname" subtitle="Atur kategori & urutan tampil per departemen" back="{{ route('operations.opname.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-3xl mx-auto w-full space-y-4">
    @if(session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 flex items-start gap-3">
            <i class="ti ti-circle-check-filled text-green-600 text-2xl shrink-0" aria-hidden="true"></i>
            <div>
                <p class="font-semibold text-green-800">Import Berhasil</p>
                <p class="text-sm text-green-700 mt-0.5">{{ session('success') }}</p>
            </div>
        </div>
    @endif
    @if(session('warning'))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 flex items-start gap-3">
            <i class="ti ti-alert-triangle text-amber-600 text-2xl shrink-0" aria-hidden="true"></i>
            <div>
                <p class="font-semibold text-amber-800">Import Selesai, Sebagian Baris Gagal</p>
                <p class="text-sm text-amber-700 mt-0.5">{{ session('warning') }}</p>
                <p class="text-xs text-amber-600 mt-1">Lihat rincian baris yang gagal di bawah ini.</p>
            </div>
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 flex items-start gap-3">
            <i class="ti ti-alert-circle-filled text-red-600 text-2xl shrink-0" aria-hidden="true"></i>
            <div>
                <p class="font-semibold text-red-800">Import Gagal</p>
                <p class="text-sm text-red-700 mt-0.5">{{ session('error') }}</p>
            </div>
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    @if(session('importErrors') && count(session('importErrors')) > 0)
        <x-sf.card title="Baris yang gagal diproses">
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
        <a href="{{ route('settings.department-category-mapping.import-template') }}" class="sf-btn-secondary min-h-11 w-full sm:w-auto px-4 inline-flex items-center justify-center">
            Unduh Template
        </a>
    </x-sf.card>

    <x-sf.card title="2. Upload File">
        <form method="POST" action="{{ route('settings.department-category-mapping.import') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="sf-label">File Excel (.xlsx/.xls/.csv, maks 10MB)</label>
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="sf-input text-base min-h-11">
                @error('file')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="rounded-xl bg-blue-50 border border-blue-200 px-3 py-2 text-xs text-blue-800">
                <p class="font-semibold mb-1">Upload ini sifatnya MENAMBAH/MEMPERBARUI, bukan mengganti semuanya:</p>
                <ul class="list-disc list-inside space-y-0.5">
                    <li>Kategori & Sub Kategori yang <strong>belum ada</strong> akan dibuat otomatis</li>
                    <li>Kategori & Sub Kategori yang <strong>namanya sudah ada</strong> (baik dari upload sebelumnya maupun dari Master Data lama) akan dipakai ulang &amp; diperbarui urutannya, TIDAK dibuat dobel</li>
                    <li>Upload ulang file yang sama, atau file baru yang cuma berisi sebagian data, sama-sama aman &mdash; data yang sudah ada sebelumnya <strong>tidak akan terhapus</strong> hanya karena tidak disebut lagi di file yang baru</li>
                </ul>
            </div>
            <button type="submit" class="sf-btn-primary min-h-11 w-full">Import Sekarang</button>
        </form>
    </x-sf.card>

    <x-sf.card title="3. Mapping Saat Ini">
        <div class="space-y-4">
            @forelse($departments as $department)
                <div>
                    <p class="font-semibold text-gray-900 text-sm">{{ $department->name }}</p>
                    @if($department->itemCategories->isEmpty())
                        <p class="text-xs text-gray-400 mt-1">Belum ada kategori yang di-mapping.</p>
                    @else
                        <div class="flex flex-wrap gap-1.5 mt-1.5">
                            @foreach($department->itemCategories as $category)
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">{{ $category->name }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-500">Belum ada departemen aktif.</p>
            @endforelse
        </div>
    </x-sf.card>
</div>
@endsection
