@extends('layouts.app')

@section('title', 'Edit Item Wipro — '.$item->name)

@php
    $photoUrl = $item->photo ? asset('storage/'.$item->photo) : null;
@endphp

@section('content')
<x-sf.page-header title="{{ $item->name }}" subtitle="Item Wipro" back="{{ route('master-data.wipro-items.index') }}" />

<div class="px-4 py-5 lg:px-6 lg:py-6 max-w-3xl mx-auto w-full space-y-5">
    <x-sf.card title="Info dari Wipro (tidak bisa diubah di sini)">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-xs text-gray-500">SKU</p>
                <p class="font-semibold text-gray-900">{{ $item->canonical_sku }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Kategori</p>
                <p class="font-semibold text-gray-900">{{ $item->category?->name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Satuan Dasar</p>
                <p class="font-semibold text-gray-900">{{ $item->baseUnit?->abbreviation ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Status</p>
                <p class="font-semibold text-gray-900">{{ $item->is_active ? 'Aktif' : 'Non-aktif' }}</p>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-3">
            Data di atas otomatis mengikuti sinkronisasi katalog Wipro — kalau perlu diubah, hubungi
            admin untuk update di sisi Wipro, bukan di sini.
        </p>
    </x-sf.card>

    <form method="POST" action="{{ route('master-data.wipro-items.update', $item) }}" enctype="multipart/form-data" class="space-y-5">
        @csrf
        @method('PUT')

        <x-sf.card title="Foto & Keterangan">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div x-data="{ photoPreview: @js($photoUrl) }">
                    <button type="button"
                            class="w-full aspect-square rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50 overflow-hidden flex items-center justify-center text-center active:scale-[.99] transition-transform"
                            @click="$refs.photoInput.click()"
                            @dragover.prevent
                            @drop.prevent="const f = $event.dataTransfer.files[0]; if (f) { $refs.photoInput.files = $event.dataTransfer.files; photoPreview = URL.createObjectURL(f); }">
                        <template x-if="photoPreview">
                            <img :src="photoPreview" alt="Preview foto item" class="h-full w-full object-cover">
                        </template>
                        <template x-if="!photoPreview">
                            <div class="px-4">
                                <div class="text-sm font-bold text-gray-500 mb-2">FOTO</div>
                                <p class="text-sm font-semibold text-gray-800">Klik/Drop Foto</p>
                                <p class="text-xs text-gray-500 mt-1">Max 2MB, JPG/PNG</p>
                            </div>
                        </template>
                    </button>
                    <input x-ref="photoInput" type="file" name="photo" accept="image/jpeg,image/png" class="hidden"
                           @change="const f = $event.target.files[0]; if (f) photoPreview = URL.createObjectURL(f);">
                </div>

                <div class="lg:col-span-2 space-y-4">
                    <x-sf.form-group label="Deskripsi" for="description">
                        <textarea id="description" name="description" rows="3" maxlength="2000"
                                  class="sf-input text-base">{{ old('description', $item->description) }}</textarea>
                    </x-sf.form-group>

                    <x-sf.form-group label="Keterangan Pembeda" for="keterangan_pembeda">
                        <input id="keterangan_pembeda" name="keterangan_pembeda" type="text" maxlength="255"
                               value="{{ old('keterangan_pembeda', $item->keterangan_pembeda) }}" class="sf-input text-base"
                               placeholder="Catatan buat bedain dari item mirip lainnya">
                    </x-sf.form-group>
                </div>
            </div>

            @if($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 mt-4">
                    {{ $errors->first() }}
                </div>
            @endif
        </x-sf.card>

        <button type="submit" class="sf-btn-primary w-full text-base">Simpan</button>
    </form>
</div>
@endsection
