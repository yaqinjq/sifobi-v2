@extends('layouts.app')

@section('title', 'Pengaturan Jenis Bahan')

@section('content')
<x-sf.page-header
    title="Pengaturan Jenis Bahan"
    subtitle="Kategori bisnis item untuk Finance"
    back="{{ route('settings.index') }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-5xl mx-auto w-full space-y-5">
    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <x-sf.card title="Daftar Jenis Bahan">
        <div class="hidden lg:grid grid-cols-[1fr_1.5fr_1fr_1fr_auto] gap-3 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-100">
            <span>Kode</span>
            <span>Nama</span>
            <span>Warna</span>
            <span>Urutan</span>
            <span class="text-right">Aksi</span>
        </div>

        <div class="divide-y divide-gray-50">
            @foreach($jenises as $jenis)
                <div class="py-3" x-data="{ editing: false }">
                    <form method="POST" action="{{ route('settings.item-jenises.update', $jenis) }}" class="grid grid-cols-1 lg:grid-cols-[1fr_1.5fr_1fr_1fr_auto] gap-3 items-center">
                        @csrf
                        @method('PUT')

                        <div>
                            <span class="lg:hidden sf-label">Kode</span>
                            <span x-show="!editing" class="font-semibold text-gray-900">{{ $jenis->code }}</span>
                            <input x-show="editing" x-cloak name="code" value="{{ $jenis->code }}" class="sf-input text-base uppercase">
                        </div>

                        <div>
                            <span class="lg:hidden sf-label">Nama</span>
                            <span x-show="!editing" class="text-gray-700">{{ $jenis->name }}</span>
                            <input x-show="editing" x-cloak name="name" value="{{ $jenis->name }}" class="sf-input text-base">
                        </div>

                        <div>
                            <span class="lg:hidden sf-label">Warna</span>
                            <span x-show="!editing" class="{{ $jenis->badgeClass() }}">{{ $jenis->color }}</span>
                            <select x-show="editing" x-cloak name="color" class="sf-input text-base">
                                @foreach($colors as $color)
                                    <option value="{{ $color }}" @selected($jenis->color === $color)>{{ $color }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <span class="lg:hidden sf-label">Urutan</span>
                            <span x-show="!editing" class="text-gray-700">{{ $jenis->sort_order }}</span>
                            <input x-show="editing" x-cloak name="sort_order" type="number" min="0" value="{{ $jenis->sort_order }}" class="sf-input text-base">
                        </div>

                        <div class="flex justify-end gap-2">
                            <x-icon-btn icon="edit" label="Edit" color="blue" x-show="!editing" @click="editing = true" />
                            <x-icon-btn icon="approve" label="Simpan" color="green" type="submit" x-show="editing" x-cloak />
                            <x-icon-btn icon="reject" label="Batal" color="gray" x-show="editing" x-cloak @click="editing = false" />
                        </div>
                    </form>

                    <form method="POST" action="{{ route('settings.item-jenises.destroy', $jenis) }}" class="mt-2 flex justify-end">
                        @csrf
                        @method('DELETE')
                        <x-icon-btn
                            icon="delete"
                            label="Hapus"
                            color="red"
                            type="submit"
                            onclick="return confirm('Yakin hapus jenis bahan ini?')"
                        />
                    </form>
                </div>
            @endforeach
        </div>
    </x-sf.card>

    <x-sf.card title="+ Tambah Jenis Bahan Baru">
        <form method="POST" action="{{ route('settings.item-jenises.store') }}" class="grid grid-cols-1 lg:grid-cols-[1fr_1.5fr_1fr_1fr_auto] gap-3 items-end">
            @csrf
            <x-sf.form-group label="Kode" for="code" :required="true">
                <input id="code" name="code" value="{{ old('code') }}" class="sf-input text-base uppercase" maxlength="50" required>
            </x-sf.form-group>
            <x-sf.form-group label="Nama" for="name" :required="true">
                <input id="name" name="name" value="{{ old('name') }}" class="sf-input text-base" maxlength="150" required>
            </x-sf.form-group>
            <x-sf.form-group label="Warna" for="color" :required="true">
                <select id="color" name="color" class="sf-input text-base" required>
                    @foreach($colors as $color)
                        <option value="{{ $color }}">{{ $color }}</option>
                    @endforeach
                </select>
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
