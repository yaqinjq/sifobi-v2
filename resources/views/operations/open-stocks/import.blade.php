@extends('layouts.app')

@section('title', 'Import Open Stock')
@section('hide-bottom-nav', 'true')

@section('topbar')
<x-sf.page-header title="Import Open Stock" subtitle="Upload Excel stok awal" back="{{ route('operations.open-stocks.index') }}" />
@endsection

@section('content')
<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-3xl mx-auto w-full space-y-5">
    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <x-sf.card title="Download Template">
        <p class="text-sm text-gray-600 mb-4">
            Template berisi sheet Open Stock, petunjuk, daftar SKU item aktif, dan daftar departemen.
        </p>
        <a href="{{ route('operations.open-stocks.import.template') }}" class="sf-btn-secondary w-full sm:w-auto">
            Download Template Excel
        </a>
    </x-sf.card>

    <x-sf.card title="Upload & Proses">
        <form method="POST"
              action="{{ route('operations.open-stocks.import.store') }}"
              enctype="multipart/form-data"
              x-data="{ fileName: '', uploading: false }"
              @submit="uploading = true"
              class="space-y-4">
            @csrf

            <x-sf.form-group label="Outlet" for="outlet_id" :required="true">
                <select id="outlet_id" name="outlet_id" class="sf-input text-base" required>
                    @foreach($outlets as $outlet)
                        <option value="{{ $outlet->id }}" @selected(auth()->user()->outlet_id == $outlet->id)>
                            {{ $outlet->name }}
                        </option>
                    @endforeach
                </select>
            </x-sf.form-group>

            <x-sf.form-group label="File Excel (.xlsx)" for="file" :required="true">
                <button type="button"
                        class="w-full rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center hover:border-primary-400 transition-colors"
                        @click="$refs.fileInput.click()">
                    <input id="file"
                           x-ref="fileInput"
                           type="file"
                           name="file"
                           accept=".xlsx,.xls"
                           class="sr-only"
                           required
                           @change="fileName = $event.target.files[0]?.name || ''">
                    <span x-show="!fileName" class="block text-sm font-semibold text-gray-600">Klik untuk pilih file Excel</span>
                    <span x-show="!fileName" class="block text-xs text-gray-400 mt-1">.xlsx atau .xls, maksimal 5MB</span>
                    <span x-show="fileName" x-cloak class="block text-sm font-semibold text-primary-800" x-text="fileName"></span>
                </button>
            </x-sf.form-group>

            <button type="submit" class="sf-btn-primary w-full" :disabled="!fileName || uploading">
                <span x-text="uploading ? 'Memproses...' : 'Upload & Proses'"></span>
            </button>
        </form>

        @if(session('import_result'))
            @php($result = session('import_result'))
            <div class="mt-4 rounded-2xl border {{ ($result['failed'] ?? 0) > 0 ? 'border-amber-100 bg-amber-50' : 'border-green-100 bg-green-50' }} px-4 py-3">
                <p class="text-sm font-semibold text-gray-900">Import selesai</p>
                <p class="text-sm text-gray-700 mt-1">Berhasil: <strong>{{ $result['inserted'] ?? 0 }}</strong> baris</p>
                <p class="text-sm text-gray-700">Gagal: <strong>{{ $result['failed'] ?? 0 }}</strong> baris</p>

                @if(($result['failed'] ?? 0) > 0)
                    <ul class="mt-3 space-y-1">
                        @foreach($result['errors'] ?? [] as $error)
                            <li class="text-xs text-red-700">{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </x-sf.card>
</div>
@endsection
