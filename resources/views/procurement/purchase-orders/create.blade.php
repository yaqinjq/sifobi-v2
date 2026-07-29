@extends('layouts.app')

@section('title', 'Buat Purchase Order')

@section('content')
<x-sf.page-header
    title="Buat Purchase Order"
    subtitle="Isi detail PO dan tambahkan item setelah disimpan"
    back="{{ route('procurement.purchase-orders.index') }}"
/>

<div class="px-4 py-5 pb-24 lg:px-6 lg:py-6 max-w-2xl mx-auto w-full">
    <form method="POST" action="{{ route('procurement.purchase-orders.store') }}" class="space-y-5">
        @csrf

        <x-sf.card title="Informasi PO">
            <div class="space-y-4">

                {{-- Tipe PO --}}
                <x-sf.form-group label="Tujuan PO" for="po_type" :required="true">
                    <select id="po_type" name="po_type" class="sf-input" required>
                        <option value="">-- Pilih tujuan --</option>
                        @foreach($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('po_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('po_type')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </x-sf.form-group>

                {{-- Outlet --}}
                <x-sf.form-group label="Outlet / Cabang" for="outlet_id" :required="true">
                    <select id="outlet_id" name="outlet_id" class="sf-input" required>
                        <option value="">-- Pilih outlet --</option>
                        @foreach($outlets as $outlet)
                            <option value="{{ $outlet->id }}" @selected(old('outlet_id') == $outlet->id)>{{ $outlet->name }}</option>
                        @endforeach
                    </select>
                    @error('outlet_id')
                        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                    @enderror
                </x-sf.form-group>

                {{-- Departemen --}}
                <x-sf.form-group label="Departemen (opsional)" for="department_id">
                    <select id="department_id" name="department_id" class="sf-input">
                        <option value="">-- Semua departemen --</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" @selected(old('department_id') == $dept->id)>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </x-sf.form-group>

                {{-- Dibutuhkan --}}
                <x-sf.form-group label="Dibutuhkan Tanggal" for="needed_at"
                    hint="Kapan barang harus tersedia (opsional)">
                    <input type="date" id="needed_at" name="needed_at"
                           value="{{ old('needed_at') }}"
                           class="sf-input" />
                </x-sf.form-group>

                {{-- Catatan --}}
                <x-sf.form-group label="Catatan" for="notes">
                    <textarea id="notes" name="notes" rows="3" class="sf-input resize-none"
                              placeholder="Informasi tambahan untuk vendor / tim purchasing...">{{ old('notes') }}</textarea>
                </x-sf.form-group>

            </div>
        </x-sf.card>

        <div class="flex gap-3 pt-1">
            <a href="{{ route('procurement.purchase-orders.index') }}" class="sf-btn-secondary flex-1 text-center">
                Batal
            </a>
            <button type="submit" class="sf-btn-primary flex-1">
                Simpan & Tambah Item
            </button>
        </div>
    </form>
</div>
@endsection
