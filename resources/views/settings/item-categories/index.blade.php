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
        <div class="hidden lg:grid grid-cols-[0.8fr_1.2fr_1.8fr_0.6fr_0.7fr_auto] gap-3 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-100">
            <span>Kode</span>
            <span>Nama</span>
            <span>Deskripsi</span>
            <span class="text-right">Urutan</span>
            <span class="text-right">Item</span>
            <span class="text-right">Aksi</span>
        </div>

        <div class="divide-y divide-gray-50">
            @foreach($categories as $category)
                <div class="py-3" x-data="{ editing: false }">
                    <form method="POST" action="{{ route('settings.item-categories.update', $category) }}" class="grid grid-cols-1 lg:grid-cols-[0.8fr_1.2fr_1.8fr_0.6fr_0.7fr_auto] gap-3 items-center">
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

                        <div class="lg:text-right">
                            <span class="lg:hidden sf-label">Jumlah Item</span>
                            @if($category->items_count > 0)
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">{{ $category->items_count }}</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500">0</span>
                            @endif
                        </div>

                        <div class="flex justify-end gap-2">
                            <x-icon-btn icon="edit" label="Edit" color="blue" x-show="!editing" @click="editing = true" />
                            <x-icon-btn icon="approve" label="Simpan" color="green" type="submit" x-show="editing" x-cloak />
                            <x-icon-btn icon="reject" label="Batal" color="gray" x-show="editing" x-cloak @click="editing = false" />
                        </div>
                    </form>

                    <form method="POST" action="{{ route('settings.item-categories.destroy', $category) }}" class="mt-2 flex justify-end">
                        @csrf
                        @method('DELETE')
                        <x-icon-btn
                            icon="delete"
                            label="Hapus"
                            color="red"
                            type="submit"
                            onclick="return confirm('Yakin hapus kategori bahan ini?')"
                        />
                    </form>
                </div>
            @endforeach
        </div>
    </x-sf.card>

    <x-sf.card title="+ Tambah Kategori Bahan">
        <form method="POST" action="{{ route('settings.item-categories.store') }}" class="grid grid-cols-1 lg:grid-cols-[0.8fr_1.2fr_1.8fr_0.6fr_auto] gap-3 items-end">
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
            <button type="submit" class="sf-btn-primary inline-flex min-h-11 items-center justify-center gap-2">
                <i class="ti ti-device-floppy text-base" aria-hidden="true"></i>
                <span>Simpan</span>
            </button>
        </form>
    </x-sf.card>
</div>
@endsection
