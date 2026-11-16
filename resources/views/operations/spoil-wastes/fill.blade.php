@extends('layouts.app')

@section('title', 'Isi Massal Spoil & Waste')

@section('content')
<x-sf.page-header title="Isi Massal lewat Excel" subtitle="Isi dari kertas hitung fisik" back="{{ route('operations.spoil-wastes.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-3xl mx-auto w-full space-y-4" x-data="spoilFillForm()">
    @if(session('success'))
        <div class="rounded-2xl border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <p class="text-sm text-gray-600">
        Download template berisi daftar item sesuai outlet+departemen (urutan sama seperti form kertas), isi kolom
        <strong>STOK</strong>, <strong>Kategori Alasan</strong>, dan <strong>TGL</strong> (boleh tanggal berbeda-beda
        per baris) seperti lembar hitung fisik, lalu upload kembali. Baris yang STOK-nya dikosongkan tidak diproses.
        Setiap baris yang berhasil akan berstatus <strong>Pending</strong> -- tetap perlu di-approve manual seperti
        catat spoil biasa.
    </p>

    @if($isSuperAdmin)
        <x-sf.card title="1. Pilih Outlet & Departemen">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="sf-label">Outlet</label>
                    <select x-model="outletId" class="sf-input text-base min-h-11">
                        <option value="">Pilih outlet</option>
                        @foreach($outlets as $outlet)
                            <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="sf-label">Departemen</label>
                    <select x-model="departmentId" class="sf-input text-base min-h-11">
                        <option value="">Pilih departemen</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-sf.card>
    @endif

    <x-sf.card :title="$isSuperAdmin ? '2. Download Template' : '1. Download Template'">
        <button type="button" @click="downloadTemplate()" :disabled="{{ $isSuperAdmin ? '!outletId || !departmentId' : 'false' }}"
                class="sf-btn-secondary min-h-11 px-4 disabled:opacity-50">
            Download Template
        </button>
    </x-sf.card>

    <x-sf.card :title="$isSuperAdmin ? '3. Upload File' : '2. Upload File'">
        <form method="POST" action="{{ route('operations.spoil-wastes.fill-import') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <template x-if="outletId"><input type="hidden" name="outlet_id" :value="outletId"></template>
            <template x-if="departmentId"><input type="hidden" name="department_id" :value="departmentId"></template>
            <div>
                <label class="sf-label">File Excel (.xlsx/.xls/.csv, maks 10MB)</label>
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="sf-input text-base min-h-11">
                @error('file')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="sf-btn-primary min-h-11 w-full">Upload &amp; Isi</button>
        </form>
    </x-sf.card>
</div>

@push('scripts')
<script>
function spoilFillForm() {
    return {
        outletId: '',
        departmentId: '',
        downloadTemplate() {
            const params = new URLSearchParams();
            if (this.outletId) params.set('outlet_id', this.outletId);
            if (this.departmentId) params.set('department_id', this.departmentId);
            window.location.href = @js(route('operations.spoil-wastes.fill-template')) + '?' + params.toString();
        },
    };
}
</script>
@endpush
@endsection
