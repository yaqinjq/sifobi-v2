@extends('layouts.app')

@section('title', 'Import Menu')

@section('content')
<x-sf.page-header
    title="Import Menu dari Excel"
    subtitle="Masukkan banyak menu sekaligus dari file export Master Menu POS"
    back="{{ route('production.menus.index') }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-2xl mx-auto w-full space-y-4">
    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <x-sf.card>
        <form method="POST" action="{{ route('production.menus.import') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <x-sf.form-group label="Brand" for="brand_id" :required="true">
                <select id="brand_id" name="brand_id" class="sf-input text-base min-h-11" required>
                    <option value="">Pilih brand</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}" @selected(old('brand_id') == $brand->id)>
                            {{ $brand->name }} ({{ $brand->outlets_count }} outlet)
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">
                    Pilih brand yang <strong>sama dengan brand outlet-outletnya</strong> -- menu cuma muncul di pencarian Catat Spoil untuk outlet ber-brand ini.
                </p>
            </x-sf.form-group>

            <x-sf.form-group label="File Excel" for="file" :required="true">
                <input id="file" type="file" name="file" accept=".xlsx,.xls" required
                       class="block w-full text-sm text-gray-500 file:mr-4 file:min-h-11 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
            </x-sf.form-group>

            <div class="rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600 space-y-1">
                <p class="font-semibold text-gray-800">Format file</p>
                <p>File export "Master Menu" dari POS apa adanya (tidak perlu diubah): judul di baris atas, header kolom di <strong>baris 6</strong>.</p>
                <p>Kolom yang dipakai: <strong>Menu Code</strong>, <strong>Menu Name</strong>, <strong>Category Detail</strong> (jadi kategori menu), <strong>Status</strong>.</p>
                <p>Menu dengan kode yang sudah ada di brand ini akan <strong>diperbarui</strong>, bukan dobel. Harga jual tidak diubah.</p>
            </div>

            <div class="flex flex-col sm:flex-row gap-2 sm:justify-end">
                <a href="{{ route('production.menus.index') }}" class="sf-btn-secondary min-h-11 px-4 text-center">Batal</a>
                <button type="submit" class="sf-btn-primary min-h-11 px-4">Import Menu</button>
            </div>
        </form>
    </x-sf.card>
</div>
@endsection
