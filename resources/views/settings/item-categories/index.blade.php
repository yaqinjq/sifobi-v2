@extends('layouts.app')

@section('title', 'Pengaturan Kategori Bahan')

@section('content')
<x-sf.page-header
    title="Pengaturan Kategori Bahan"
    subtitle="Level klasifikasi bahan yang lebih spesifik"
    back="{{ route('settings.index') }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-5xl mx-auto w-full space-y-5">
    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <x-sf.card title="Daftar Kategori Bahan">
        <div class="hidden lg:grid grid-cols-[1fr_1.4fr_2fr_1fr_auto] gap-3 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-100">
            <span>Kode</span>
            <span>Nama</span>
            <span>Deskripsi</span>
            <span>Urutan</span>
            <span class="text-right">Aksi</span>
        </div>

        <div class="divide-y divide-gray-50">
            @foreach($categories as $category)
                <div class="py-3" x-data="{ editing: false }">
                    <form method="POST" action="{{ route('settings.item-categories.update', $category) }}" class="grid grid-cols-1 lg:grid-cols-[1fr_1.4fr_2fr_1fr_auto] gap-3 items-center">
                        @csrf
                        @method('PUT')

                        <div>
                            <span class="lg:hidden sf-label">Kode</span>
                            <span x-show="!editing" class="font-semibold text-gray-900">{{ $category->code }}</span>
                            <input x-show="editing" x-cloak name="code" value="{{ $category->code }}" class="sf-input text-base uppercase">
                        </div>

                        <div>
                            <span class="lg:hidden sf-label">Nama</span>
                            <span x-show="!editing" class="text-gray-700">{{ $category->name }}</span>
                            <input x-show="editing" x-cloak name="name" value="{{ $category->name }}" class="sf-input text-base">
                        </div>

                        <div>
                            <span class="lg:hidden sf-label">Deskripsi</span>
                            <span x-show="!editing" class="text-gray-500">{{ $category->description ?: '-' }}</span>
                            <input x-show="editing" x-cloak name="description" value="{{ $category->description }}" class="sf-input text-base">
                        </div>

                        <div>
                            <span class="lg:hidden sf-label">Urutan</span>
                            <span x-show="!editing" class="text-gray-700">{{ $category->sort_order }}</span>
                            <input x-show="editing" x-cloak name="sort_order" type="number" min="0" value="{{ $category->sort_order }}" class="sf-input text-base">
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" x-show="!editing" class="sf-btn-secondary text-xs px-3 py-1.5 min-h-11" @click="editing = true">Edit</button>
                            <button type="submit" x-show="editing" x-cloak class="sf-btn-primary text-xs px-3 py-1.5 min-h-11">Simpan</button>
                            <button type="button" x-show="editing" x-cloak class="sf-btn-secondary text-xs px-3 py-1.5 min-h-11" @click="editing = false">Batal</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('settings.item-categories.destroy', $category) }}" class="mt-2 flex justify-end">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="sf-btn-danger text-xs px-3 py-1.5 min-h-11">Hapus</button>
                    </form>
                </div>
            @endforeach
        </div>
    </x-sf.card>

    <x-sf.card title="+ Tambah Kategori Bahan">
        <form method="POST" action="{{ route('settings.item-categories.store') }}" class="grid grid-cols-1 lg:grid-cols-[1fr_1.4fr_2fr_1fr_auto] gap-3 items-end">
            @csrf
            <x-sf.form-group label="Kode" for="code" :required="true">
                <input id="code" name="code" value="{{ old('code') }}" class="sf-input text-base uppercase" maxlength="50" required>
            </x-sf.form-group>
            <x-sf.form-group label="Nama" for="name" :required="true">
                <input id="name" name="name" value="{{ old('name') }}" class="sf-input text-base" maxlength="150" required>
            </x-sf.form-group>
            <x-sf.form-group label="Deskripsi" for="description">
                <input id="description" name="description" value="{{ old('description') }}" class="sf-input text-base" maxlength="2000">
            </x-sf.form-group>
            <x-sf.form-group label="Urutan" for="sort_order">
                <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', 0) }}" class="sf-input text-base">
            </x-sf.form-group>
            <button type="submit" class="sf-btn-primary">Simpan</button>
        </form>
    </x-sf.card>
</div>
@endsection
