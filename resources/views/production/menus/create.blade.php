@extends('layouts.app')

@section('title', 'Tambah Menu')

@section('content')
<x-sf.page-header
    title="Tambah Menu"
    subtitle="Menu baru untuk mulai riset resep"
    back="{{ route('production.menus.index') }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-2xl mx-auto w-full">
    <x-sf.card>
        <form method="POST" action="{{ route('production.menus.store') }}" class="space-y-4">
            @csrf

            <x-sf.form-group label="Brand" for="brand_id" :required="true">
                <select id="brand_id" name="brand_id" class="sf-input text-base" required>
                    <option value="">Pilih brand</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}" @selected(old('brand_id') == $brand->id)>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </x-sf.form-group>

            <x-sf.form-group label="Nama Menu" for="name" :required="true">
                <input id="name" name="name" value="{{ old('name') }}" class="sf-input text-base" maxlength="150" placeholder="Contoh: Kopi Susu Gula Aren" required>
            </x-sf.form-group>

            <x-sf.form-group label="Kode (opsional)" for="code">
                <input id="code" name="code" value="{{ old('code') }}" class="sf-input text-base" maxlength="32">
            </x-sf.form-group>

            @if($errors->any())
                <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <button type="submit" class="sf-btn-primary w-full min-h-12">Simpan Menu</button>
        </form>
    </x-sf.card>
</div>
@endsection
