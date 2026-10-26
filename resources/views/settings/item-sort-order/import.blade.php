@extends('layouts.app')

@section('title', 'Urutan Item per Departemen')

@section('content')
<x-sf.page-header title="Urutan Item per Departemen" subtitle="Atur urutan item di dalam sub kategori sesuai form fisik" back="{{ route('operations.opname.index') }}" />

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

    <div class="rounded-xl bg-blue-50 border border-blue-200 px-3 py-2 text-xs text-blue-800">
        Fitur ini melengkapi <a href="{{ route('settings.department-category-mapping.import-form') }}" class="underline font-semibold">Mapping Departemen &amp; Kategori Opname</a> -- pastikan kategori &amp; sub kategori departemen sudah di-mapping dulu di sana, baru atur urutan item di sini. Item yang belum diisi urutannya tetap tampil, tapi di posisi paling belakang sub kategorinya.
    </div>

    <x-sf.card title="1. Unduh Template">
        <p class="text-sm text-gray-600 mb-3">Pilih departemen, lalu unduh template -- sudah berisi semua item departemen tsb, terkelompok sesuai Kategori/Sub Kategori. Tinggal isi kolom "Urutan" sesuai posisi di form fisik.</p>
        <form method="GET" action="{{ route('settings.item-sort-order.template') }}" class="flex flex-wrap gap-2">
            <select name="department_id" required class="sf-input py-2 text-sm flex-1 min-w-[180px] min-h-11">
                <option value="">Pilih departemen...</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="sf-btn-secondary min-h-11 px-4">Unduh Template</button>
        </form>
    </x-sf.card>

    <x-sf.card title="2. Upload Urutan">
        <form method="POST" action="{{ route('settings.item-sort-order.import') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="sf-label">Departemen</label>
                <select name="department_id" required class="sf-input text-base min-h-11">
                    <option value="">Pilih departemen...</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
                @error('department_id')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="sf-label">File Excel (.xlsx/.xls/.csv, maks 10MB)</label>
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="sf-input text-base min-h-11">
                @error('file')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="sf-btn-primary min-h-11 w-full">Import Sekarang</button>
        </form>
    </x-sf.card>

    <x-sf.card title="3. Status Saat Ini">
        <div class="space-y-2">
            @forelse($departments as $department)
                <div class="flex items-center justify-between gap-3">
                    <p class="font-semibold text-gray-900 text-sm">{{ $department->name }}</p>
                    <span class="text-xs text-gray-500">{{ $department->item_sort_orders_count }} item sudah diurutkan</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">Belum ada departemen aktif.</p>
            @endforelse
        </div>
    </x-sf.card>
</div>
@endsection
