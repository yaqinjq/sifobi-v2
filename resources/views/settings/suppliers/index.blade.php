@extends('layouts.app')

@section('title', 'Pengaturan Supplier')

@section('content')
<x-sf.page-header
    title="Pengaturan Supplier"
    subtitle="Supplier eksternal dan internal penerimaan barang"
    back="{{ route('settings.index') }}"
/>

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-6xl mx-auto w-full space-y-5">
    @if($errors->any())
        <div class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <x-sf.card title="Daftar Supplier">
        <div class="hidden lg:grid grid-cols-[1fr_2fr_1fr_1fr_1fr_auto] gap-3 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 border-b border-gray-100">
            <span>Kode</span>
            <span>Nama</span>
            <span>Kontak</span>
            <span>Telepon</span>
            <span>Status</span>
            <span class="text-right">Aksi</span>
        </div>

        <div class="divide-y divide-gray-50">
            @foreach($suppliers as $supplier)
                <div class="py-3" x-data="{ editing: false }">
                    <form method="POST" action="{{ route('settings.suppliers.update', $supplier) }}" class="grid grid-cols-1 lg:grid-cols-[1fr_2fr_1fr_1fr_1fr_auto] gap-3 items-center">
                        @csrf
                        @method('PUT')

                        <div>
                            <span class="lg:hidden sf-label">Kode</span>
                            <span x-show="!editing" class="font-semibold text-gray-900">{{ $supplier->code }}</span>
                            <input x-show="editing" x-cloak name="code" value="{{ $supplier->code }}" class="sf-input text-base uppercase" maxlength="50">
                        </div>

                        <div>
                            <span class="lg:hidden sf-label">Nama</span>
                            <span x-show="!editing" class="text-gray-700">{{ $supplier->name }}</span>
                            <input x-show="editing" x-cloak name="name" value="{{ $supplier->name }}" class="sf-input text-base" maxlength="255">
                        </div>

                        <div>
                            <span class="lg:hidden sf-label">Kontak</span>
                            <span x-show="!editing" class="text-gray-700">{{ $supplier->contact_name ?: '-' }}</span>
                            <input x-show="editing" x-cloak name="contact_name" value="{{ $supplier->contact_name }}" class="sf-input text-base" maxlength="150">
                        </div>

                        <div>
                            <span class="lg:hidden sf-label">Telepon</span>
                            <span x-show="!editing" class="text-gray-700">{{ $supplier->phone ?: '-' }}</span>
                            <input x-show="editing" x-cloak name="phone" value="{{ $supplier->phone }}" class="sf-input text-base" maxlength="50">
                        </div>

                        <div>
                            <span class="lg:hidden sf-label">Status</span>
                            <span x-show="!editing" class="{{ $supplier->is_active ? 'badge-active' : 'badge-inactive' }}">
                                {{ $supplier->is_active ? 'AKTIF' : 'NONAKTIF' }}
                            </span>
                            <label x-show="editing" x-cloak class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-gray-700">
                                <input type="checkbox" name="is_active" value="1" @checked($supplier->is_active) class="rounded border-gray-300 text-primary-700 focus:ring-primary-500">
                                Aktif
                            </label>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" x-show="!editing" class="sf-btn-secondary text-xs px-3 py-1.5 min-h-11" @click="editing = true">Edit</button>
                            <button type="submit" x-show="editing" x-cloak class="sf-btn-primary text-xs px-3 py-1.5 min-h-11">Simpan</button>
                            <button type="button" x-show="editing" x-cloak class="sf-btn-secondary text-xs px-3 py-1.5 min-h-11" @click="editing = false">Batal</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('settings.suppliers.destroy', $supplier) }}" class="mt-2 flex justify-end">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="sf-btn-danger text-xs px-3 py-1.5 min-h-11">Hapus</button>
                    </form>
                </div>
            @endforeach
        </div>
    </x-sf.card>

    <x-sf.card title="+ Tambah Supplier">
        <form method="POST" action="{{ route('settings.suppliers.store') }}" class="grid grid-cols-1 lg:grid-cols-5 gap-3 items-end">
            @csrf
            <x-sf.form-group label="Kode" for="code" :required="true">
                <input id="code" name="code" value="{{ old('code') }}" class="sf-input text-base uppercase" maxlength="50" required>
            </x-sf.form-group>
            <x-sf.form-group label="Nama" for="name" :required="true">
                <input id="name" name="name" value="{{ old('name') }}" class="sf-input text-base" maxlength="255" required>
            </x-sf.form-group>
            <x-sf.form-group label="Kontak" for="contact_name">
                <input id="contact_name" name="contact_name" value="{{ old('contact_name') }}" class="sf-input text-base" maxlength="150">
            </x-sf.form-group>
            <x-sf.form-group label="Telepon" for="phone">
                <input id="phone" name="phone" value="{{ old('phone') }}" class="sf-input text-base" maxlength="50">
            </x-sf.form-group>
            <button type="submit" class="sf-btn-primary min-h-11">Simpan</button>
        </form>
    </x-sf.card>
</div>
@endsection
