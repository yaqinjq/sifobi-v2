@extends('layouts.app')

@section('title', 'Satuan')

@section('content')
<x-sf.page-header title="Satuan" subtitle="Master unit inventory">
    <x-slot:actions>
        @can('manage_units')
            <a href="{{ route('master-data.units.create') }}"
               class="hidden sm:inline-flex sf-btn-primary text-xs px-3 py-2 min-h-11">
                + Tambah
            </a>
        @endcan
    </x-slot:actions>
</x-sf.page-header>

<div class="px-4 py-4 lg:px-6 lg:py-6 max-w-5xl mx-auto w-full"
     x-data="{ search: '', deleteOpen: false, deleteUrl: '', deleteName: '', matches(text) { return text.toLowerCase().includes(this.search.toLowerCase()) } }">
    <div class="sticky top-[65px] lg:top-0 z-20 bg-gray-50/95 backdrop-blur pb-3">
        <label for="unit-search" class="sr-only">Cari satuan</label>
        <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
            </svg>
            <input id="unit-search"
                   type="search"
                   x-model="search"
                   placeholder="Cari kode, nama, atau singkatan..."
                   class="sf-input pl-10 text-base">
        </div>
    </div>

    <div class="lg:hidden space-y-3">
        @forelse($units as $unit)
            @php
                $searchable = strtolower($unit->code.' '.$unit->name.' '.$unit->abbreviation);
            @endphp
            <div x-show="matches(@js($searchable))" class="sf-card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="font-semibold text-gray-900 text-base truncate">{{ $unit->name }}</h2>
                        <p class="text-sm text-gray-500 mt-1">Kode: {{ $unit->code }}</p>
                        <p class="text-sm text-gray-500">Singkatan: {{ $unit->abbreviation }}</p>
                    </div>
                    @can('manage_units')
                        <div class="flex items-center gap-2">
                            <x-icon-btn
                                icon="edit"
                                label="Edit"
                                color="blue"
                                href="{{ route('master-data.units.edit', $unit) }}"
                            />
                            <x-icon-btn
                                icon="delete"
                                label="Hapus"
                                color="red"
                                @click="deleteOpen = true; deleteUrl = @js(route('master-data.units.destroy', $unit)); deleteName = @js($unit->name)"
                            />
                        </div>
                    @endcan
                </div>
            </div>
        @empty
            <x-sf.empty-state
                title="Belum ada satuan"
                description="Tambahkan satuan dasar seperti GR, ML, PCS sebelum membuat item."
                :action="auth()->user()->can('manage_units') ? route('master-data.units.create') : null"
                actionLabel="+ Tambah Satuan"
            />
        @endforelse
    </div>

    <div class="hidden lg:block">
        @if($units->isEmpty())
            <x-sf.empty-state
                title="Belum ada satuan"
                description="Tambahkan satuan dasar seperti GR, ML, PCS sebelum membuat item."
                :action="auth()->user()->can('manage_units') ? route('master-data.units.create') : null"
                actionLabel="+ Tambah Satuan"
            />
        @else
            <div class="sf-card overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">No</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Kode</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Nama</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Singkatan</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Jumlah Konversi</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($units as $index => $unit)
                            @php
                                $searchable = strtolower($unit->code.' '.$unit->name.' '.$unit->abbreviation);
                                $conversionCount = $unit->conversions_from_count + $unit->conversions_to_count;
                            @endphp
                            <tr x-show="matches(@js($searchable))" class="odd:bg-white even:bg-gray-50/60">
                                <td class="px-4 py-3 text-gray-400">{{ $index + 1 }}</td>
                                <td class="px-4 py-3 font-semibold text-gray-900">{{ $unit->code }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $unit->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $unit->abbreviation }}</td>
                                <td class="px-4 py-3 text-right text-gray-700">{{ $conversionCount }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        @can('manage_units')
                                            <x-icon-btn
                                                icon="edit"
                                                label="Edit"
                                                color="blue"
                                                size="sm"
                                                href="{{ route('master-data.units.edit', $unit) }}"
                                            />
                                            <x-icon-btn
                                                icon="delete"
                                                label="Hapus"
                                                color="red"
                                                size="sm"
                                                @click="deleteOpen = true; deleteUrl = @js(route('master-data.units.destroy', $unit)); deleteName = @js($unit->name)"
                                            />
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @can('manage_units')
        <a href="{{ route('master-data.units.create') }}"
           class="lg:hidden fixed right-5 bottom-[calc(5.5rem+env(safe-area-inset-bottom))] z-40 h-14 w-14 rounded-full bg-primary-800 text-white shadow-lg flex items-center justify-center active:scale-95 transition-transform"
           aria-label="Tambah satuan">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
        </a>
    @endcan

    <div x-show="deleteOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-gray-900/40 p-4">
        <div class="sf-card w-full max-w-sm p-4" @click.outside="deleteOpen = false">
            <h2 class="font-heading font-semibold text-gray-900">Hapus satuan?</h2>
            <p class="text-sm text-gray-500 mt-2">
                Satuan <span class="font-semibold text-gray-800" x-text="deleteName"></span> hanya bisa dihapus jika belum dipakai item atau konversi.
            </p>
            <div class="mt-4 flex gap-2">
                <button type="button" class="sf-btn-secondary flex-1" @click="deleteOpen = false">Batal</button>
                <form method="POST" :action="deleteUrl" class="flex-1">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="sf-btn-danger w-full">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
